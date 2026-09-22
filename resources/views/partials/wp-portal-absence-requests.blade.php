<div class="wp-stack wp-portal-absence">
    <form wire:submit="submitAbsence" class="wp-card wp-card-pad wp-stack">
        <h2 class="wp-section-title">{{ __('time.portal.absence.form_title') }}</h2>
        <div class="wp-field">
            <label class="wp-label" for="absence-kind">{{ __('time.portal.absence.kind') }}</label>
            <select id="absence-kind" class="wp-select" wire:model="absenceKind">
                <option value="{{ \App\Enums\ShiftTypeKind::Leave->value }}">{{ __('time.schedule.types.kinds.leave') }}</option>
                <option value="{{ \App\Enums\ShiftTypeKind::Recup->value }}">{{ __('time.schedule.types.kinds.recup') }}</option>
            </select>
            @error('absenceKind') <p class="wp-error">{{ $message }}</p> @enderror
        </div>
        <div class="wp-field">
            <label class="wp-label" for="absence-from">{{ __('time.portal.absence.date_from') }}</label>
            <input id="absence-from" class="wp-input" type="date" wire:model="absenceDateFrom">
            @error('absenceDateFrom') <p class="wp-error">{{ $message }}</p> @enderror
        </div>
        <div class="wp-field">
            <label class="wp-label" for="absence-to">{{ __('time.portal.absence.date_to') }}</label>
            <input id="absence-to" class="wp-input" type="date" wire:model="absenceDateTo">
            @error('absenceDateTo') <p class="wp-error">{{ $message }}</p> @enderror
        </div>
        <div class="wp-field" x-data="{ n: 0, max: {{ \App\Support\Validation\TextDescriptionLimits::MAX }} }">
            <label class="wp-label" for="absence-description">{{ __('time.portal.absence.description') }}</label>
            <textarea
                id="absence-description"
                class="wp-textarea"
                rows="3"
                maxlength="{{ \App\Support\Validation\TextDescriptionLimits::MAX }}"
                wire:model="absenceDescription"
                x-on:input="n = $el.value.length"
            ></textarea>
            @error('absenceDescription') <p class="wp-error">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn btn--primary btn--block">{{ __('time.portal.absence.submit') }}</button>
    </form>

    <div class="wp-stack-tight">
        <h2 class="wp-section-title">{{ __('time.portal.absence.list_title') }}</h2>
        @if ($absenceRequests->isEmpty())
            <div class="wp-card wp-card-pad">
                <p class="wp-muted">{{ __('time.portal.absence.empty') }}</p>
            </div>
        @else
            <div class="wp-list wp-portal-hours-list">
                @foreach ($absenceRequests as $request)
                    <div class="wp-card wp-portal-hours-day wp-stack-tight" wire:key="absence-{{ $request->id }}">
                        <div class="wp-cluster">
                            <strong>{{ __('time.schedule.types.kinds.'.$request->kind->value) }}</strong>
                            <span @class([
                                'wp-pill',
                                'wp-pill--progress' => $request->status === \App\Enums\AbsenceRequestStatus::Pending,
                                'wp-pill--done' => $request->status === \App\Enums\AbsenceRequestStatus::Approved,
                                'wp-pill--closed' => $request->status !== \App\Enums\AbsenceRequestStatus::Pending
                                    && $request->status !== \App\Enums\AbsenceRequestStatus::Approved,
                            ])>{{ __('time.absence.status.'.$request->status->value) }}</span>
                        </div>
                        <p>
                            {{ $request->date_from->toDateString() }}
                            @if ($request->date_from->toDateString() !== $request->date_to->toDateString())
                                – {{ $request->date_to->toDateString() }}
                            @endif
                        </p>
                        @if ($request->description)
                            <p>{{ $request->description }}</p>
                        @endif
                        @if ($request->decision_description)
                            <p class="wp-muted">{{ __('time.portal.absence.decision') }}: {{ $request->decision_description }}</p>
                        @endif
                        @if ($request->workerMayWithdraw())
                            <button
                                type="button"
                                class="btn btn--surface btn--block btn--sm"
                                wire:confirm="{{ $request->status->isApproved()
                                    ? __('time.portal.absence.cancel_approved_confirm')
                                    : __('time.portal.absence.cancel_confirm') }}"
                                wire:click="cancelAbsence({{ $request->id }})"
                            >
                                {{ __('time.portal.absence.cancel') }}
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
