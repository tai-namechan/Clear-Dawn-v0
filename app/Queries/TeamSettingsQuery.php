<?php

namespace App\Queries;

use App\Enums\TeamMembershipRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMembership;
use App\Models\TeamUser;
use Illuminate\Support\Collection;

class TeamSettingsQuery
{
    /** @return array<string, mixed> */
    public function handle(Team $team): array
    {
        /** @var Collection<int, TeamMembership> $staffMemberships */
        $staffMemberships = TeamMembership::query()
            ->where('team_id', $team->id)
            ->where('member_type', 'team_user')
            ->whereIn('role', TeamMembershipRole::staffValues())
            ->orderBy('role')
            ->get();

        $staffUsers = TeamUser::query()
            ->whereIn('id', $staffMemberships->pluck('member_id'))
            ->get(['id', 'name'])
            ->keyBy('id');

        /** @var Collection<int, TeamInvitation> $invitations */
        $invitations = TeamInvitation::query()
            ->where('team_id', $team->id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->orderBy('expires_at')
            ->get(['id', 'email', 'role', 'invitee_type', 'expires_at']);

        return [
            'team' => [
                'name' => $team->name,
                'slug' => $team->slug,
                'organization_type' => $team->organization_type,
                'status' => $team->status,
                'timezone' => $team->timezone,
            ],
            'staff' => $staffMemberships->map(function (TeamMembership $membership) use ($staffUsers): array {
                $user = $staffUsers->get($membership->member_id);
                $role = $membership->role instanceof TeamMembershipRole
                    ? $membership->role->value
                    : (string) $membership->role;

                return [
                    'id' => $membership->id,
                    'name' => $user?->name ?? '不明なスタッフ',
                    'role' => $role,
                    'role_label' => $this->roleLabel($role),
                    'status' => $membership->status,
                    'joined_at' => $membership->joined_at?->toIso8601String(),
                ];
            })->values()->all(),
            'invitations' => $invitations->map(fn (TeamInvitation $invitation): array => [
                'id' => $invitation->id,
                'email_masked' => $this->maskEmail($invitation->email),
                'role' => $invitation->role,
                'role_label' => $this->roleLabel($invitation->role),
                'invitee_type' => $invitation->invitee_type,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
            ])->values()->all(),
            'prototype_note' => 'プロトタイプでは編集対象外です。登録・変更・削除は本番実装へ持ち越します。',
        ];
    }

    private function maskEmail(?string $email): string
    {
        if ($email === null || $email === '' || ! str_contains($email, '@')) {
            return '（未設定）';
        }

        [$local, $domain] = explode('@', $email, 2);
        $prefix = mb_substr($local, 0, 1);

        return $prefix.'***@'.$domain;
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'owner' => 'オーナー',
            'admin' => '管理者',
            'head_coach' => 'ヘッドコーチ',
            'coach' => 'コーチ',
            'nutrition_staff' => '栄養スタッフ',
            'conditioning_staff' => 'コンディショニング',
            'athlete' => '選手',
            default => $role,
        };
    }
}
