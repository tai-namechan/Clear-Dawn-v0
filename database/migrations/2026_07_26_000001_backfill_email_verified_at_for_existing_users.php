<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * User に MustVerifyEmail を実装した際の既存ユーザーのロックアウトを防ぐ。
 *
 * 監査 C-1（docs/audit/2026-07-26-pre-release-audit.md）まで
 * EnsureEmailIsVerified は no-op であり、確認メールも送信されていなかった。
 * そのため既存ユーザーの email_verified_at は null のまま運用されている。
 *
 * MustVerifyEmail を実装した瞬間、これらのユーザーは全ルートから締め出される。
 * 本人には確認メールが届いた履歴すらないため、自力での復旧手段がない。
 *
 * 移行時点の既存ユーザーは「検証を要求されない仕様の下で登録され、
 * 暗黙的に信頼されていた」ものとして created_at で検証済みとみなす。
 * 以後の新規登録には適用されず、正しく検証が要求される。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => DB::raw('created_at')]);
    }

    /**
     * 意図的に何もしない。
     *
     * ロールバックで検証済み状態を剥奪すると、up() が防いだはずの
     * ロックアウトがそのまま発生する。しかも「元は null だった行」と
     * 「本当に検証を済ませた行」を区別する情報が残っていないため、
     * 正確な巻き戻し自体が不可能である。
     */
    public function down(): void
    {
        // no-op（理由は上記 PHPDoc を参照）
    }
};
