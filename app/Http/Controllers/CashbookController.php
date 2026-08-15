<?php

namespace App\Http\Controllers;

use App\Models\CashbookEntry;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CashbookController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', CashbookEntry::class);

        $q = CashbookEntry::query()->with(['group:id,name', 'recorder:id,name']);
        $this->scopeToActiveGroup($q);
        $this->hideRegularizationsForNonAdmins($q);

        if ($g    = $request->integer('group_id'))           $q->where('group_id', $g);
        if ($t    = $request->string('type')->toString())    $q->where('type', $t);
        if ($cat  = $request->string('category')->toString())$q->where('category', $cat);
        if ($from = $request->string('from')->toString())    $q->whereDate('occurred_on', '>=', $from);
        if ($to   = $request->string('to')->toString())      $q->whereDate('occurred_on', '<=', $to);

        // Totals respect the same filters as the listing.
        $totalsQuery = (clone $q);
        $income  = (float) (clone $totalsQuery)->where('type', 'income')->sum('amount');
        $expense = (float) (clone $totalsQuery)->where('type', 'expense')->sum('amount');

        $entries = $q->orderByDesc('occurred_on')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('cashbook.index', [
            'entries' => $entries,
            'groups'  => $this->accessibleGroupOptions(),
            'income'  => $income,
            'expense' => $expense,
            'net'     => $income - $expense,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', CashbookEntry::class);

        $type = in_array($request->string('type')->toString(), ['income', 'expense'], true)
            ? $request->string('type')->toString()
            : 'expense';

        return view('cashbook.create', [
            'groups'     => $this->accessibleGroupOptions(),
            'activeId'   => session('active_group_id'),
            'type'       => $type,
            'categories' => CashbookEntry::categoriesFor($type),
            'regularize' => false,
        ]);
    }

    public function regularizeCreate()
    {
        $this->authorize('regularize', CashbookEntry::class);

        return view('cashbook.create', [
            'groups'     => $this->accessibleGroupOptions(),
            'activeId'   => session('active_group_id'),
            'type'       => 'expense',
            'categories' => [CashbookEntry::REGULARIZATION_CATEGORY => 'Regularization'],
            'regularize' => true,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', CashbookEntry::class);

        $data = $request->validate([
            'group_id'     => ['required', 'exists:groups,id'],
            'type'         => ['required', 'in:income,expense'],
            'category'     => ['required', 'string', 'max:60'],
            'amount'       => ['required', 'numeric', 'min:0.01'],
            'method'       => ['required', 'in:cash,mobile_money,bank,cheque,other'],
            'channel_ref'  => ['nullable', 'string', 'max:120'],
            'counterparty' => ['nullable', 'string', 'max:160'],
            'occurred_on'  => ['required', 'date'],
            'notes'        => ['nullable', 'string'],
        ]);

        if ($data['category'] === CashbookEntry::REGULARIZATION_CATEGORY) {
            abort(403, 'Use the admin-only Regularize action for this entry.');
        }

        if (! auth()->user()->canAccessGroup((int) $data['group_id'])) {
            abort(403, 'You cannot record cashbook entries in that group.');
        }

        $valid = array_keys(CashbookEntry::categoriesFor($data['type']));
        if (! in_array($data['category'], $valid, true)) {
            return back()->withInput()->withErrors([
                'category' => 'Pick a valid category for this entry type.',
            ]);
        }

        $data['reference']   = $this->nextReference($data['type']);
        $data['recorded_by'] = auth()->id();

        $entry = CashbookEntry::create($data);

        ActivityLogger::log(
            groupId:    $entry->group_id,
            type:       'cashbook.'.$entry->type.'.created',
            description: ($entry->type === 'income' ? 'recorded a deposit' : 'recorded a withdrawal')
                .' ('.CashbookEntry::categoriesFor($entry->type)[$entry->category].')',
            subject:    $entry,
            icon:       $entry->type === 'income' ? 'arrow-down-circle' : 'arrow-up-circle',
            color:      $entry->type === 'income' ? 'green' : 'red',
            data:       [
                'amount'    => number_format((float) $entry->amount, 2),
                'reference' => $entry->reference,
            ],
        );

        return redirect()
            ->route('cashbook.show', $entry)
            ->with('status', ucfirst($entry->type)." {$entry->reference} recorded.");
    }

    public function regularizeStore(Request $request)
    {
        $this->authorize('regularize', CashbookEntry::class);

        $data = $request->validate([
            'group_id'     => ['required', 'exists:groups,id'],
            'amount'       => ['required', 'numeric', 'min:0.01'],
            'method'       => ['required', 'in:cash,mobile_money,bank,cheque,other'],
            'channel_ref'  => ['nullable', 'string', 'max:120'],
            'counterparty' => ['nullable', 'string', 'max:160'],
            'occurred_on'  => ['required', 'date'],
            'notes'        => ['nullable', 'string'],
        ]);

        if (! auth()->user()->canAccessGroup((int) $data['group_id'])) {
            abort(403, 'You cannot regularize cashbook entries in that group.');
        }

        $entry = CashbookEntry::create([
            ...$data,
            'reference'   => $this->nextReference('expense', 'REG'),
            'type'        => 'expense',
            'category'    => CashbookEntry::REGULARIZATION_CATEGORY,
            'recorded_by' => auth()->id(),
        ]);

        // Intentionally private: no ActivityLogger call here.
        $this->notifyRegularizationAdmins($entry);

        return redirect()
            ->route('cashbook.show', $entry)
            ->with('status', "Regularization {$entry->reference} recorded.");
    }

    public function show(CashbookEntry $cashbook)
    {
        $this->authorize('view', $cashbook);
        $cashbook->load(['group', 'recorder']);
        return view('cashbook.show', ['entry' => $cashbook]);
    }

    public function edit(CashbookEntry $cashbook)
    {
        $this->authorize('update', $cashbook);
        $cashbook->load(['group', 'recorder']);
        return view('cashbook.edit', [
            'entry'      => $cashbook,
            'groups'     => $this->accessibleGroupOptions(),
            'categories' => CashbookEntry::categoriesFor($cashbook->type),
            'regularize' => $cashbook->isRegularization(),
        ]);
    }

    public function update(Request $request, CashbookEntry $cashbook)
    {
        $this->authorize('update', $cashbook);

        $data = $request->validate([
            'category'     => ['required', 'string', 'max:60'],
            'amount'       => ['required', 'numeric', 'min:0.01'],
            'method'       => ['required', 'in:cash,mobile_money,bank,cheque,other'],
            'channel_ref'  => ['nullable', 'string', 'max:120'],
            'counterparty' => ['nullable', 'string', 'max:160'],
            'occurred_on'  => ['required', 'date'],
            'notes'        => ['nullable', 'string'],
        ]);

        if ($cashbook->isRegularization()) {
            $data['category'] = CashbookEntry::REGULARIZATION_CATEGORY;
        } elseif ($data['category'] === CashbookEntry::REGULARIZATION_CATEGORY) {
            abort(403, 'Use the admin-only Regularize action for this entry.');
        }

        $valid = array_keys(CashbookEntry::categoriesFor($cashbook->type));
        if (! $cashbook->isRegularization() && ! in_array($data['category'], $valid, true)) {
            return back()->withInput()->withErrors(['category' => 'Pick a valid category for this entry type.']);
        }

        $cashbook->update($data);

        ActivityLogger::log(
            groupId:     $cashbook->group_id,
            type:        'cashbook.'.$cashbook->type.'.updated',
            description: 'updated cashbook entry '.$cashbook->reference,
            subject:     $cashbook,
            icon:        'edit',
            color:       'blue',
            data:        ['amount' => number_format((float) $cashbook->amount, 2), 'reference' => $cashbook->reference],
        );

        return redirect()->route('cashbook.show', $cashbook)->with('status', "Entry {$cashbook->reference} updated.");
    }

    public function destroy(CashbookEntry $cashbook)
    {
        $this->authorize('delete', $cashbook);

        ActivityLogger::log(
            groupId:    $cashbook->group_id,
            type:       'cashbook.deleted',
            description: "removed cashbook entry {$cashbook->reference}",
            subject:    $cashbook,
            icon:       'trash',
            color:      'red',
        );

        $cashbook->delete();
        return redirect()->route('cashbook.index')->with('status', "Entry {$cashbook->reference} removed.");
    }

    protected function nextReference(string $type, ?string $customPrefix = null): string
    {
        $prefix = $customPrefix ?: ($type === 'income' ? 'IN' : 'EX');
        do {
            $ref = $prefix.'-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
        } while (CashbookEntry::where('reference', $ref)->exists());
        return $ref;
    }

    /**
     * Regularization entries are private balance corrections. Administrators
     * can see them; all other roles see the normal cashbook only.
     */
    protected function hideRegularizationsForNonAdmins(Builder $query): Builder
    {
        if (auth()->user()?->hasAnyRole(['super_admin', 'group_admin'])) {
            return $query;
        }

        return $query->where('category', '!=', CashbookEntry::REGULARIZATION_CATEGORY);
    }

    /**
     * Notify administrators about a private cashbook regularization.
     */
    protected function notifyRegularizationAdmins(CashbookEntry $entry): void
    {
        $title = 'Cashbook regularization recorded';
        $body = sprintf(
            '%s recorded a regularization of %s %s for %s.',
            auth()->user()?->name ?? 'An administrator',
            number_format((float) $entry->amount, 2),
            $entry->group?->currency ?? '',
            $entry->group?->name ?? 'the group',
        );
        $link = route('cashbook.show', $entry, false);

        UserNotification::notifyGroupRoles(
            groupId: $entry->group_id,
            roles: ['group_admin'],
            type: 'cashbook.regularization.created',
            title: $title,
            body: $body,
            link: $link,
            icon: 'adjustments',
            color: 'orange',
        );

        User::whereHas('roles', fn ($query) => $query->where('name', 'super_admin'))
            ->pluck('id')
            ->each(fn (int $userId) => UserNotification::notify(
                userId: $userId,
                type: 'cashbook.regularization.created',
                title: $title,
                body: $body,
                link: $link,
                icon: 'adjustments',
                color: 'orange',
            ));
    }
}
