<?php

namespace App\Http\Controllers\Yoyu\Money;

use App\Domain\Yoyu\Money\Models\MoneyAccount;
use App\Domain\Yoyu\Money\Models\MoneyImport;
use App\Domain\Yoyu\Money\Services\MoneyCsvImportService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Yoyu\Money\Concerns\EnsuresMoneyOwnership;
use App\Http\Requests\Yoyu\Money\ConfigureMoneyImportRequest;
use App\Http\Requests\Yoyu\Money\StoreMoneyImportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class MoneyImportController extends Controller
{
    use EnsuresMoneyOwnership;

    public function index(Request $request): Response
    {
        $user = $request->user();

        $imports = MoneyImport::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (MoneyImport $import): array => [
                'id' => $import->id,
                'account_id' => $import->account_id,
                'status' => $import->status->value,
                'source_filename' => $import->source_filename,
                'row_count' => $import->row_count,
                'created_at' => $import->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Yoyu/Money/Imports/Index', [
            'imports' => $imports,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        $accounts = MoneyAccount::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type'])
            ->map(fn (MoneyAccount $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type->value,
            ]);

        return Inertia::render('Yoyu/Money/Imports/Create', [
            'accounts' => $accounts,
        ]);
    }

    public function store(
        StoreMoneyImportRequest $request,
        MoneyCsvImportService $importService,
    ): RedirectResponse {
        $data = $request->validated();
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        $import = $importService->upload(
            $request->user(),
            $file,
            (string) $data['account_id'],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'CSVをアップロードしました。']);

        return redirect()->route('yoyu.money.imports.index')->with('import_id', $import->id);
    }

    public function configure(
        ConfigureMoneyImportRequest $request,
        MoneyImport $import,
        MoneyCsvImportService $importService,
    ): RedirectResponse {
        $this->ensureOwned($request->user(), $import);

        $importService->configure($request->user(), $import, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => '取込設定を保存しました。']);

        return redirect()->back();
    }

    public function execute(
        Request $request,
        MoneyImport $import,
        MoneyCsvImportService $importService,
    ): RedirectResponse {
        $this->ensureOwned($request->user(), $import);

        $importService->execute($request->user(), $import);

        Inertia::flash('toast', ['type' => 'success', 'message' => '取込を開始しました。']);

        return redirect()->back();
    }

    public function rollback(
        Request $request,
        MoneyImport $import,
        MoneyCsvImportService $importService,
    ): RedirectResponse {
        $this->ensureOwned($request->user(), $import);

        $importService->rollback($request->user(), $import);

        Inertia::flash('toast', ['type' => 'success', 'message' => '取込を取り消しました。']);

        return redirect()->back();
    }
}
