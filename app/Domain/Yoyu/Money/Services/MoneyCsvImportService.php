<?php

namespace App\Domain\Yoyu\Money\Services;

use App\Domain\Yoyu\Money\Enums\MoneyDirection;
use App\Domain\Yoyu\Money\Enums\MoneyImportRowStatus;
use App\Domain\Yoyu\Money\Enums\MoneyImportStatus;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionKind;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionSource;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionStatus;
use App\Domain\Yoyu\Money\Exceptions\MoneyDomainException;
use App\Domain\Yoyu\Money\Jobs\ProcessMoneyImportJob;
use App\Domain\Yoyu\Money\Models\MoneyAccount;
use App\Domain\Yoyu\Money\Models\MoneyImport;
use App\Domain\Yoyu\Money\Models\MoneyImportRow;
use App\Domain\Yoyu\Money\Models\MoneyReconciliation;
use App\Domain\Yoyu\Money\Models\MoneyTransaction;
use App\Domain\Yoyu\Money\Support\MoneyCsvNormalizer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use SplFileObject;
use Throwable;

final class MoneyCsvImportService
{
    private const DISK = 'local';

    private const PATH_PREFIX = 'yoyu-money-imports';

    private const PREVIEW_LIMIT = 100;

    private const RETENTION_DAYS = 7;

    public function __construct(
        private readonly MoneyAuditService $auditService,
        private readonly MoneyCsvNormalizer $normalizer,
    ) {}

    public function upload(User $user, UploadedFile $file, string $accountId): MoneyImport
    {
        /** @var MoneyAccount|null $account */
        $account = MoneyAccount::query()
            ->withoutUserScope()
            ->whereKey($accountId)
            ->where('user_id', $user->id)
            ->first();

        abort_unless($account !== null, 404);

        $contents = $file->get();
        if ($contents === false) {
            throw new InvalidArgumentException('Unable to read uploaded file.');
        }

        $checksum = hash('sha256', $contents);
        $idempotencyKey = hash('sha256', implode('|', [
            (string) $user->id,
            $accountId,
            $checksum,
            'default',
        ]));

        $existing = MoneyImport::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $ulid = (string) Str::ulid();
        $path = self::PATH_PREFIX.'/'.$user->id.'/'.$ulid.'.csv';
        Storage::disk(self::DISK)->put($path, $contents);

        /** @var MoneyImport $import */
        $import = MoneyImport::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'account_id' => $accountId,
            'status' => MoneyImportStatus::Uploaded,
            'source_filename' => $file->getClientOriginalName(),
            'source_storage_path' => $path,
            'source_checksum' => $checksum,
            'idempotency_key' => $idempotencyKey,
            'mapping_config' => null,
        ]);

        $this->auditService->record(
            (int) $user->id,
            'money_import.uploaded',
            MoneyImport::class,
            (string) $import->id,
            null,
            [
                'id' => $import->id,
                'account_id' => $accountId,
                'status' => $import->status->value,
            ],
        );

        return $import;
    }

    /**
     * @param  array<string, mixed>  $mapping
     * @return array{import: MoneyImport, preview: list<array<string, mixed>>}
     */
    public function configure(User $user, MoneyImport $import, array $mapping): array
    {
        $this->assertOwned($user, $import);

        if (! in_array($import->status, [
            MoneyImportStatus::Uploaded,
            MoneyImportStatus::Mapped,
            MoneyImportStatus::Previewed,
        ], true)) {
            throw new InvalidArgumentException('Import cannot be configured in its current status.');
        }

        $mapping = $this->normalizeMapping($mapping);
        $idempotencyKey = hash('sha256', implode('|', [
            (string) $user->id,
            (string) $import->account_id,
            (string) $import->source_checksum,
            json_encode($mapping, JSON_THROW_ON_ERROR),
        ]));

        return DB::transaction(function () use ($user, $import, $mapping, $idempotencyKey): array {
            /** @var MoneyImport|null $locked */
            $locked = MoneyImport::query()
                ->withoutUserScope()
                ->whereKey($import->id)
                ->lockForUpdate()
                ->first();

            abort_unless($locked !== null, 404);

            MoneyImportRow::query()
                ->withoutUserScope()
                ->where('import_id', $locked->id)
                ->delete();

            $rows = $this->readCsvRows($locked, $mapping, self::PREVIEW_LIMIT);
            $preview = [];

            foreach ($rows as $row) {
                $normalized = $this->normalizeRow($row['cells'], $mapping, (string) $locked->account_id);
                $status = $normalized['ok']
                    ? MoneyImportRowStatus::Pending
                    : MoneyImportRowStatus::Error;

                MoneyImportRow::query()->withoutUserScope()->create([
                    'user_id' => $user->id,
                    'import_id' => $locked->id,
                    'row_number' => $row['row_number'],
                    'raw_payload' => $row['raw'],
                    'normalized_payload' => $normalized['payload'],
                    'status' => $status,
                    'issue_codes' => $normalized['issues'],
                ]);

                $preview[] = [
                    'row_number' => $row['row_number'],
                    'raw' => $row['raw'],
                    'normalized' => $normalized['payload'],
                    'issues' => $normalized['issues'],
                ];
            }

            $locked->mapping_config = $mapping;
            $locked->idempotency_key = $idempotencyKey;
            $locked->status = MoneyImportStatus::Previewed;
            $locked->row_count = count($rows);
            $locked->save();

            $this->auditService->record(
                (int) $user->id,
                'money_import.previewed',
                MoneyImport::class,
                (string) $locked->id,
                null,
                [
                    'id' => $locked->id,
                    'status' => $locked->status->value,
                    'row_count' => $locked->row_count,
                ],
            );

            return [
                'import' => $locked->refresh(),
                'preview' => $preview,
            ];
        });
    }

    public function execute(User $user, MoneyImport $import, bool $sync = false): MoneyImport
    {
        $this->assertOwned($user, $import);

        if ($import->status !== MoneyImportStatus::Previewed
            && $import->status !== MoneyImportStatus::Failed) {
            throw new InvalidArgumentException('Import must be previewed before execute.');
        }

        if ($import->mapping_config === null) {
            throw new InvalidArgumentException('Import mapping_config is required.');
        }

        $import->status = MoneyImportStatus::Processing;
        $import->started_at = Date::now();
        $import->error_message = null;
        $import->save();

        if ($sync || app()->environment('testing') || config('queue.default') === 'sync') {
            $this->processImport($import);

            return $import->refresh();
        }

        ProcessMoneyImportJob::dispatch($import->id);

        return $import->refresh();
    }

    public function processImport(MoneyImport $import): void
    {
        /** @var MoneyImport|null $locked */
        $locked = MoneyImport::query()
            ->withoutUserScope()
            ->whereKey($import->id)
            ->first();

        if ($locked === null) {
            return;
        }

        if ($locked->status === MoneyImportStatus::Completed) {
            return;
        }

        $mapping = $this->normalizeMapping($locked->mapping_config ?? []);
        $accountId = (string) $locked->account_id;

        try {
            DB::transaction(function () use ($locked, $mapping, $accountId): void {
                MoneyImportRow::query()
                    ->withoutUserScope()
                    ->where('import_id', $locked->id)
                    ->delete();

                $accepted = 0;
                $rejected = 0;
                $duplicates = 0;
                $rowCount = 0;

                foreach ($this->streamCsvRows($locked, $mapping) as $row) {
                    $rowCount++;
                    $normalized = $this->normalizeRow($row['cells'], $mapping, $accountId);

                    if (! $normalized['ok']) {
                        MoneyImportRow::query()->withoutUserScope()->create([
                            'user_id' => $locked->user_id,
                            'import_id' => $locked->id,
                            'row_number' => $row['row_number'],
                            'raw_payload' => $row['raw'],
                            'normalized_payload' => $normalized['payload'],
                            'status' => MoneyImportRowStatus::Error,
                            'issue_codes' => $normalized['issues'],
                        ]);
                        $rejected++;

                        continue;
                    }

                    /** @var array{
                     *     occurred_on: string,
                     *     description_raw: string,
                     *     description_normalized: string,
                     *     amount_minor: int,
                     *     direction: string,
                     *     row_hash: string,
                     *     external_id: string|null
                     * } $payload */
                    $payload = $normalized['payload'];

                    $strongDup = $this->findStrongDuplicate($locked, $accountId, $payload);
                    if ($strongDup !== null) {
                        MoneyImportRow::query()->withoutUserScope()->create([
                            'user_id' => $locked->user_id,
                            'import_id' => $locked->id,
                            'row_number' => $row['row_number'],
                            'raw_payload' => $row['raw'],
                            'normalized_payload' => $payload,
                            'status' => MoneyImportRowStatus::SkippedDuplicate,
                            'issue_codes' => ['strong_duplicate'],
                            'duplicate_of_transaction_id' => $strongDup->id,
                        ]);
                        $duplicates++;

                        continue;
                    }

                    $probableDup = $this->findProbableDuplicate($locked, $accountId, $payload);
                    if ($probableDup !== null) {
                        MoneyImportRow::query()->withoutUserScope()->create([
                            'user_id' => $locked->user_id,
                            'import_id' => $locked->id,
                            'row_number' => $row['row_number'],
                            'raw_payload' => $row['raw'],
                            'normalized_payload' => $payload,
                            'status' => MoneyImportRowStatus::NeedsReview,
                            'issue_codes' => ['probable_duplicate'],
                            'duplicate_of_transaction_id' => $probableDup->id,
                        ]);
                        $duplicates++;

                        continue;
                    }

                    $kind = $payload['direction'] === MoneyDirection::Inflow->value
                        ? MoneyTransactionKind::Income
                        : MoneyTransactionKind::Purchase;

                    /** @var MoneyImportRow $importRow */
                    $importRow = MoneyImportRow::query()->withoutUserScope()->create([
                        'user_id' => $locked->user_id,
                        'import_id' => $locked->id,
                        'row_number' => $row['row_number'],
                        'raw_payload' => $row['raw'],
                        'normalized_payload' => $payload,
                        'status' => MoneyImportRowStatus::Pending,
                        'issue_codes' => [],
                    ]);

                    /** @var MoneyTransaction $transaction */
                    $transaction = MoneyTransaction::query()->withoutUserScope()->create([
                        'user_id' => $locked->user_id,
                        'account_id' => $accountId,
                        'direction' => MoneyDirection::from($payload['direction']),
                        'kind' => $kind,
                        'amount_minor' => $payload['amount_minor'],
                        'currency_code' => 'JPY',
                        'occurred_on' => $payload['occurred_on'],
                        'posted_on' => $payload['occurred_on'],
                        'description_raw' => $payload['description_raw'],
                        'description_normalized' => $payload['description_normalized'],
                        'status' => MoneyTransactionStatus::Posted,
                        'source' => MoneyTransactionSource::Csv,
                        'external_id' => $payload['external_id'] ?? ('csv:'.$payload['row_hash']),
                        'import_id' => $locked->id,
                        'import_row_id' => $importRow->id,
                    ]);

                    $importRow->status = MoneyImportRowStatus::Imported;
                    $importRow->transaction_id = $transaction->id;
                    $importRow->save();
                    $accepted++;
                }

                $locked->row_count = $rowCount;
                $locked->accepted_count = $accepted;
                $locked->rejected_count = $rejected;
                $locked->duplicate_count = $duplicates;
                $locked->status = MoneyImportStatus::Completed;
                $locked->finished_at = Date::now();
                $locked->error_message = null;
                $locked->save();
            });

            // Intentionally does NOT update account balance.
            $this->auditService->record(
                (int) $locked->user_id,
                'money_import.completed',
                MoneyImport::class,
                (string) $locked->id,
                null,
                [
                    'id' => $locked->id,
                    'status' => MoneyImportStatus::Completed->value,
                    'row_count' => $locked->refresh()->row_count,
                    'accepted_count' => $locked->accepted_count,
                    'rejected_count' => $locked->rejected_count,
                    'duplicate_count' => $locked->duplicate_count,
                ],
            );
        } catch (Throwable $e) {
            $locked->status = MoneyImportStatus::Failed;
            $locked->error_message = $e->getMessage();
            $locked->finished_at = Date::now();
            $locked->save();

            throw $e;
        }
    }

    public function rollback(User $user, MoneyImport $import): MoneyImport
    {
        $this->assertOwned($user, $import);

        if ($import->status === MoneyImportStatus::RolledBack) {
            return $import;
        }

        if ($import->status !== MoneyImportStatus::Completed) {
            throw new InvalidArgumentException('Only completed imports can be rolled back.');
        }

        return DB::transaction(function () use ($user, $import): MoneyImport {
            /** @var MoneyImport|null $locked */
            $locked = MoneyImport::query()
                ->withoutUserScope()
                ->whereKey($import->id)
                ->lockForUpdate()
                ->first();

            abort_unless($locked !== null, 404);

            if ($locked->status === MoneyImportStatus::RolledBack) {
                return $locked;
            }

            $transactions = MoneyTransaction::query()
                ->withoutUserScope()
                ->where('import_id', $locked->id)
                ->whereNull('voided_at')
                ->get();

            $blockers = [];
            foreach ($transactions as $transaction) {
                if ($transaction->edited_at !== null) {
                    $blockers[] = "transaction:{$transaction->id}:edited";
                }

                $hasReconciliation = MoneyReconciliation::query()
                    ->withoutUserScope()
                    ->where('transaction_id', $transaction->id)
                    ->exists();

                if ($hasReconciliation) {
                    $blockers[] = "transaction:{$transaction->id}:reconciled";
                }
            }

            if ($blockers !== []) {
                throw new MoneyDomainException(
                    'Import rollback blocked by edited or reconciled transactions.',
                    $blockers,
                );
            }

            foreach ($transactions as $transaction) {
                $transaction->status = MoneyTransactionStatus::Voided;
                $transaction->voided_at = Date::now();
                $transaction->void_reason = 'import_rollback';
                $transaction->save();
            }

            MoneyImportRow::query()
                ->withoutUserScope()
                ->where('import_id', $locked->id)
                ->where('status', MoneyImportRowStatus::Imported->value)
                ->update(['status' => MoneyImportRowStatus::Voided->value]);

            $locked->status = MoneyImportStatus::RolledBack;
            $locked->finished_at = Date::now();
            $locked->save();

            $this->auditService->record(
                (int) $user->id,
                'money_import.rolled_back',
                MoneyImport::class,
                (string) $locked->id,
                null,
                [
                    'id' => $locked->id,
                    'status' => $locked->status->value,
                ],
            );

            return $locked->refresh();
        });
    }

    public function purgeExpired(): int
    {
        $cutoff = Date::now()->subDays(self::RETENTION_DAYS);
        $purged = 0;

        MoneyImport::query()
            ->withoutUserScope()
            ->whereIn('status', [
                MoneyImportStatus::Completed->value,
                MoneyImportStatus::RolledBack->value,
            ])
            ->whereNotNull('source_storage_path')
            ->where('finished_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($imports) use (&$purged): void {
                foreach ($imports as $import) {
                    /** @var MoneyImport $import */
                    $path = $import->source_storage_path;
                    if ($path === null || $path === '') {
                        continue;
                    }

                    if (Storage::disk(self::DISK)->exists($path)) {
                        Storage::disk(self::DISK)->delete($path);
                    }

                    $import->source_storage_path = null;
                    $import->save();
                    $purged++;
                }
            });

        return $purged;
    }

    /**
     * @param  array<string, mixed>  $mapping
     * @return array<string, mixed>
     */
    private function normalizeMapping(array $mapping): array
    {
        return [
            'date_column' => $mapping['date_column'] ?? null,
            'description_column' => $mapping['description_column'] ?? null,
            'amount_column' => $mapping['amount_column'] ?? null,
            'debit_column' => $mapping['debit_column'] ?? null,
            'credit_column' => $mapping['credit_column'] ?? null,
            'external_id_column' => $mapping['external_id_column'] ?? null,
            'date_format' => $mapping['date_format'] ?? null,
            'amount_sign' => $mapping['amount_sign'] ?? 'expense_positive',
            'encoding' => $mapping['encoding'] ?? 'UTF-8',
            'delimiter' => $mapping['delimiter'] ?? ',',
            'has_header' => (bool) ($mapping['has_header'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $mapping
     * @return list<array{row_number: int, raw: array<string, string>, cells: array<string, string>}>
     */
    private function readCsvRows(MoneyImport $import, array $mapping, ?int $limit): array
    {
        $rows = [];
        foreach ($this->streamCsvRows($import, $mapping) as $row) {
            $rows[] = $row;
            if ($limit !== null && count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $mapping
     * @return \Generator<int, array{row_number: int, raw: array<string, string>, cells: array<string, string>}>
     */
    private function streamCsvRows(MoneyImport $import, array $mapping): \Generator
    {
        $path = $import->source_storage_path;
        if ($path === null || ! Storage::disk(self::DISK)->exists($path)) {
            throw new InvalidArgumentException('Import source file is missing.');
        }

        $absolute = Storage::disk(self::DISK)->path($path);
        $encoding = strtoupper((string) ($mapping['encoding'] ?? 'UTF-8'));
        $delimiter = (string) ($mapping['delimiter'] ?? ',');
        if ($delimiter === '') {
            $delimiter = ',';
        }
        $hasHeader = (bool) ($mapping['has_header'] ?? true);

        $file = new SplFileObject($absolute, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl($delimiter);

        $headers = null;
        $dataRowNumber = 0;

        foreach ($file as $index => $csvRow) {
            if (! is_array($csvRow)) {
                continue;
            }

            /** @var list<string|null> $csvRow */
            if ($this->isEmptyCsvRow($csvRow)) {
                continue;
            }

            $decoded = array_map(
                fn (?string $cell): string => $this->decodeCell((string) ($cell ?? ''), $encoding),
                $csvRow,
            );

            if ($hasHeader && $headers === null) {
                $headers = [];
                foreach ($decoded as $i => $header) {
                    $key = trim($header);
                    $headers[$i] = $key !== '' ? $key : (string) $i;
                }

                continue;
            }

            if ($headers === null) {
                $headers = [];
                foreach (array_keys($decoded) as $i) {
                    $headers[$i] = (string) $i;
                }
            }

            $raw = [];
            $cells = [];
            foreach ($decoded as $i => $value) {
                $key = $headers[$i] ?? (string) $i;
                $raw[$key] = $value;
                $cells[$key] = $value;
                $cells[(string) $i] = $value;
            }

            $dataRowNumber++;

            yield [
                'row_number' => $dataRowNumber,
                'raw' => $raw,
                'cells' => $cells,
            ];

            unset($index);
        }
    }

    /**
     * @param  list<string|null>  $row
     */
    private function isEmptyCsvRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function decodeCell(string $value, string $encoding): string
    {
        if (in_array($encoding, ['UTF-8', 'UTF8'], true)) {
            return $value;
        }

        if (in_array($encoding, ['SJIS', 'SHIFT_JIS', 'SHIFT-JIS', 'SJIS-WIN', 'CP932'], true)) {
            $converted = mb_convert_encoding($value, 'UTF-8', 'SJIS-win');

            return $converted !== false ? $converted : $value;
        }

        $converted = @mb_convert_encoding($value, 'UTF-8', $encoding);

        return $converted !== false ? $converted : $value;
    }

    /**
     * @param  array<string, string>  $cells
     * @param  array<string, mixed>  $mapping
     * @return array{ok: bool, payload: array<string, mixed>, issues: list<string>}
     */
    private function normalizeRow(array $cells, array $mapping, string $accountId): array
    {
        $issues = [];
        $payload = [];

        try {
            $dateCol = $mapping['date_column'] ?? null;
            if ($dateCol === null) {
                throw new InvalidArgumentException('date_column is required.');
            }
            $occurredOn = $this->normalizer->parseDate($cells[(string) $dateCol] ?? '', $mapping);
            $payload['occurred_on'] = $occurredOn;
        } catch (Throwable $e) {
            $issues[] = 'invalid_date';
        }

        $descCol = $mapping['description_column'] ?? null;
        $descriptionRaw = $descCol !== null ? trim((string) ($cells[(string) $descCol] ?? '')) : '';
        $payload['description_raw'] = $descriptionRaw;
        $payload['description_normalized'] = $this->normalizer->normalizeDescription($descriptionRaw);

        try {
            $amount = $this->normalizer->resolveAmount($cells, $mapping);
            $payload['amount_minor'] = $amount['amount_minor'];
            $payload['direction'] = $amount['direction'];
        } catch (Throwable) {
            $issues[] = 'invalid_amount';
        }

        $externalCol = $mapping['external_id_column'] ?? null;
        $externalId = $externalCol !== null ? trim((string) ($cells[(string) $externalCol] ?? '')) : '';
        $payload['external_id'] = $externalId !== '' ? $externalId : null;

        if ($issues === [] && isset($payload['occurred_on'], $payload['amount_minor'])) {
            $payload['row_hash'] = $this->normalizer->rowHash(
                $accountId,
                (string) $payload['occurred_on'],
                (int) $payload['amount_minor'],
                (string) $payload['description_normalized'],
            );
        }

        return [
            'ok' => $issues === [],
            'payload' => $payload,
            'issues' => $issues,
        ];
    }

    /**
     * @param  array{
     *     occurred_on: string,
     *     amount_minor: int,
     *     description_normalized: string,
     *     row_hash: string,
     *     external_id: string|null
     * }  $payload
     */
    private function findStrongDuplicate(MoneyImport $import, string $accountId, array $payload): ?MoneyTransaction
    {
        if ($payload['external_id'] !== null) {
            /** @var MoneyTransaction|null $byExternal */
            $byExternal = MoneyTransaction::query()
                ->withoutUserScope()
                ->where('user_id', $import->user_id)
                ->where('external_id', $payload['external_id'])
                ->whereNull('voided_at')
                ->first();

            if ($byExternal !== null) {
                return $byExternal;
            }
        }

        $hashExternal = 'csv:'.$payload['row_hash'];

        /** @var MoneyTransaction|null $byHash */
        $byHash = MoneyTransaction::query()
            ->withoutUserScope()
            ->where('user_id', $import->user_id)
            ->where(function ($query) use ($hashExternal, $payload): void {
                $query->where('external_id', $hashExternal)
                    ->orWhere('external_id', $payload['row_hash']);
            })
            ->whereNull('voided_at')
            ->first();

        if ($byHash !== null) {
            return $byHash;
        }

        /** @var MoneyTransaction|null $exact */
        $exact = MoneyTransaction::query()
            ->withoutUserScope()
            ->where('user_id', $import->user_id)
            ->where('account_id', $accountId)
            ->whereDate('occurred_on', $payload['occurred_on'])
            ->where('amount_minor', $payload['amount_minor'])
            ->where('description_normalized', $payload['description_normalized'])
            ->whereNull('voided_at')
            ->first();

        return $exact;
    }

    /**
     * @param  array{occurred_on: string, amount_minor: int, description_normalized: string}  $payload
     */
    private function findProbableDuplicate(MoneyImport $import, string $accountId, array $payload): ?MoneyTransaction
    {
        /** @var MoneyTransaction|null $match */
        $match = MoneyTransaction::query()
            ->withoutUserScope()
            ->where('user_id', $import->user_id)
            ->where('account_id', $accountId)
            ->whereDate('occurred_on', $payload['occurred_on'])
            ->where('amount_minor', $payload['amount_minor'])
            ->where('description_normalized', '!=', $payload['description_normalized'])
            ->whereNull('voided_at')
            ->first();

        return $match;
    }

    private function assertOwned(User $user, MoneyImport $import): void
    {
        abort_unless((int) $import->user_id === (int) $user->id, 404);
    }
}
