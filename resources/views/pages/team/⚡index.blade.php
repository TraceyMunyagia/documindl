<?php

use App\Actions\Businesses\InviteMember;
use App\Enums\BusinessRole;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.documind')] #[Title('Team')] class extends Component
{
    public string $email = '';

    public string $role = 'employee';

    public ?string $notice = null;

    #[Computed]
    public function business(): Business
    {
        return auth()->user()->currentBusiness;
    }

    #[Computed]
    public function myRole(): BusinessRole
    {
        return auth()->user()->roleIn($this->business);
    }

    #[Computed]
    public function members()
    {
        return $this->business->members()->orderBy('name')->get();
    }

    #[Computed]
    public function invitations()
    {
        return $this->business->invitations()->pending()->latest()->get();
    }

    public function invite(InviteMember $inviteMember): void
    {
        $this->authorize('inviteMembers', $this->business);

        $allowed = array_map(fn (BusinessRole $role): string => $role->value, $this->myRole->assignableRoles());

        $this->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in($allowed)],
        ]);

        $inviteMember->handle($this->business, auth()->user(), $this->email, BusinessRole::from($this->role));

        $this->notice = "Invitation sent to {$this->email}.";
        $this->reset('email');
        unset($this->invitations);
    }

    public function changeRole(int $userId, string $role): void
    {
        $this->authorize('manageMembers', $this->business);

        $newRole = BusinessRole::from($role);
        abort_if($newRole->isOwner(), 422, 'A business can only have one owner.');
        abort_if($userId === $this->business->owner_id, 403, "The owner's role can't be changed.");

        $this->business->members()->updateExistingPivot($userId, ['role' => $newRole->value]);
        AuditLog::record('member.role_changed', $this->business, ['user_id' => $userId, 'role' => $newRole->value]);

        $this->notice = 'Role updated.';
        unset($this->members);
    }

    public function removeMember(int $userId): void
    {
        $this->authorize('manageMembers', $this->business);
        abort_if($userId === $this->business->owner_id, 403, "The owner can't be removed.");

        $this->business->members()->detach($userId);
        User::query()
            ->whereKey($userId)
            ->where('current_business_id', $this->business->id)
            ->update(['current_business_id' => null]);

        AuditLog::record('member.removed', $this->business, ['user_id' => $userId]);

        $this->notice = 'Member removed.';
        unset($this->members);
    }

    public function revokeInvitation(int $invitationId): void
    {
        $this->authorize('inviteMembers', $this->business);

        $this->business->invitations()->whereKey($invitationId)->delete();

        $this->notice = 'Invitation revoked.';
        unset($this->invitations);
    }
};
?>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        @if ($notice)
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $notice }}</div>
        @endif

        <div class="rounded-xl border border-zinc-200 bg-white shadow-sm">
            <div class="border-b border-zinc-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-zinc-900">Members of {{ $this->business->name }}</h2>
            </div>

            <ul class="divide-y divide-zinc-100">
                @foreach ($this->members as $member)
                    @php $memberRole = $member->membership->role; @endphp

                    <li class="flex flex-wrap items-center gap-4 px-5 py-3" wire:key="member-{{ $member->id }}">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-zinc-900">
                                {{ $member->name }}
                                @if ($member->is(auth()->user()))
                                    <span class="text-xs font-normal text-zinc-400">(you)</span>
                                @endif
                            </p>
                            <p class="truncate text-xs text-zinc-500">{{ $member->email }}</p>
                        </div>

                        @if ($this->myRole->isOwner() && ! $memberRole->isOwner())
                            <select
                                wire:change="changeRole({{ $member->id }}, $event.target.value)"
                                class="rounded-lg border border-zinc-300 px-2 py-1 text-sm"
                                aria-label="Role for {{ $member->name }}"
                            >
                                @foreach ([BusinessRole::Manager, BusinessRole::Employee] as $option)
                                    <option value="{{ $option->value }}" @selected($option === $memberRole)>{{ $option->label() }}</option>
                                @endforeach
                            </select>

                            <button
                                type="button"
                                wire:click="removeMember({{ $member->id }})"
                                wire:confirm="Remove {{ $member->name }} from {{ $this->business->name }}?"
                                class="text-sm text-rose-600 hover:text-rose-700"
                            >
                                Remove
                            </button>
                        @else
                            <x-ui.badge :color="$memberRole->isOwner() ? 'indigo' : 'zinc'">{{ $memberRole->label() }}</x-ui.badge>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($this->invitations->isNotEmpty())
            <div class="rounded-xl border border-zinc-200 bg-white shadow-sm">
                <div class="border-b border-zinc-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-zinc-900">Pending invitations</h2>
                </div>
                <ul class="divide-y divide-zinc-100">
                    @foreach ($this->invitations as $invitation)
                        <li class="flex items-center gap-4 px-5 py-3 text-sm" wire:key="invite-{{ $invitation->id }}">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-zinc-900">{{ $invitation->email }}</p>
                                <p class="text-xs text-zinc-500">
                                    {{ $invitation->role->label() }} · expires
                                    {{ $invitation->expires_at->timezone(config('documind.display_timezone'))->format('j M Y') }}
                                </p>
                            </div>
                            @can('inviteMembers', $this->business)
                                <button type="button" wire:click="revokeInvitation({{ $invitation->id }})" class="text-sm text-zinc-500 hover:text-rose-600">
                                    Revoke
                                </button>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <div>
        @can('inviteMembers', $this->business)
            <form wire:submit="invite" class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-zinc-900">Invite someone</h2>
                <p class="mt-1 text-xs text-zinc-500">They'll get an email with a link to join {{ $this->business->name }}.</p>

                <div class="mt-4 space-y-3">
                    <div>
                        <label for="invite-email" class="block text-sm font-medium text-zinc-700">Email</label>
                        <input
                            id="invite-email"
                            type="email"
                            wire:model="email"
                            placeholder="name@example.com"
                            class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                        >
                        @error('email') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="invite-role" class="block text-sm font-medium text-zinc-700">Role</label>
                        <select
                            id="invite-role"
                            wire:model="role"
                            class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm shadow-sm"
                        >
                            @foreach ($this->myRole->assignableRoles() as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        @error('role') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="mt-4 w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
                >
                    Send invitation
                </button>
            </form>
        @else
            <div class="rounded-xl border border-zinc-200 bg-white p-5 text-sm text-zinc-500 shadow-sm">
                Only owners and managers can invite people to {{ $this->business->name }}.
            </div>
        @endcan
    </div>
</div>
