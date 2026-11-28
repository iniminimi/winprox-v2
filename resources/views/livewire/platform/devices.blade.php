<div class="wp-stack" x-data="wpUsbFlash()">
    <x-wp-page-head-title
        icon="document"
        :title="__('ops.devices.title')"
        :subtitle="__('ops.devices.subtitle')"
    />

    @if ($flashMessage)
        <div @class([
            'wp-flash',
            'wp-flash--success' => $flashType !== 'error',
            'wp-flash--danger' => $flashType === 'error',
        ])>{{ $flashMessage }}</div>
    @endif

    <div class="wp-card wp-card-pad wp-stack">
        <p class="wp-subhead">{{ __('ops.portal.qr_title') }}</p>
        <p class="wp-muted">{{ __('ops.portal.qr_hint') }}</p>
        <div class="wp-cluster wp-cluster--wrap">
            <div class="wp-ops-qr" aria-hidden="true">{!! $opsQrSvg !!}</div>
            <div class="wp-stack-tight wp-grow">
                <p class="wp-text-body"><code>{{ $opsPortalUrl }}</code></p>
                <p class="wp-muted wp-text-sm">{{ __('ops.portal.qr_phone') }}</p>
            </div>
        </div>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-cluster wp-cluster--wrap">
            <div class="wp-grow">
                <label class="wp-label" for="ops-device-search">{{ __('ops.devices.search') }}</label>
                <input id="ops-device-search" type="search" class="wp-input"
                       wire:model.live.debounce.300ms="search"
                       placeholder="{{ __('ops.devices.search_placeholder') }}" autocomplete="off">
            </div>
            <div>
                <label class="wp-label" for="ops-device-status">{{ __('ops.devices.filter_status') }}</label>
                <select id="ops-device-status" class="wp-input" wire:model.live="status">
                    <option value="">{{ __('ops.devices.filter_all') }}</option>
                    @foreach ($statuses as $statusCase)
                        <option value="{{ $statusCase->value }}">{{ __($statusCase->labelKey()) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="wp-label" for="ops-device-type">{{ __('ops.devices.filter_type') }}</label>
                <select id="ops-device-type" class="wp-input" wire:model.live="type">
                    <option value="">{{ __('ops.devices.filter_all') }}</option>
                    @foreach ($types as $typeCase)
                        <option value="{{ $typeCase->value }}">{{ __($typeCase->labelKey()) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="wp-label" for="ops-device-tenant">{{ __('ops.devices.filter_tenant') }}</label>
                <select id="ops-device-tenant" class="wp-input" wire:model.live="tenantFilter">
                    <option value="">{{ __('ops.devices.filter_all') }}</option>
                    <option value="none">{{ __('ops.devices.filter_no_tenant') }}</option>
                    @foreach ($tenants as $tenant)
                        <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <label class="wp-check" for="ops-without-mac">
            <input id="ops-without-mac" type="checkbox" wire:model.live="withoutMac">
            <span>{{ __('ops.devices.filter_without_mac') }}</span>
        </label>
        <div class="wp-cluster">
            <button type="button" class="btn btn--primary" wire:click="openRegister">
                {{ __('ops.devices.register') }}
            </button>
            <button type="button" class="btn btn--ghost" @click="detectLocal()" :disabled="busy">
                <span x-show="!busy">{{ __('ops.devices.flash') }}</span>
                <span x-show="busy" x-cloak>{{ __('ops.devices.flash_working') }}</span>
            </button>
        </div>

        @if ($devices->isEmpty())
            <p class="wp-muted">{{ __('ops.devices.empty') }}</p>
        @else
            <div class="wp-list wp-list--entity-rows">
                @foreach ($devices as $device)
                    <div class="wp-list-row" wire:key="device-{{ $device->id }}">
                        <div class="wp-grow">
                            <p class="wp-text-body">
                                <strong>{{ $device->serial_number }}</strong>
                                <span class="wp-pill">{{ __($device->status->labelKey()) }}</span>
                            </p>
                            <p class="wp-muted wp-text-sm">
                                {{ $device->brand }}
                                @if ($device->model)
                                    · {{ $device->model }}
                                @endif
                                · {{ __($device->type->labelKey()) }}
                                @if ($device->sku)
                                    · SKU {{ $device->sku }}
                                @endif
                                @if ($device->mac)
                                    · MAC {{ \App\Support\Ops\HardwareMac::formatForDisplay($device->mac) }}
                                @else
                                    · {{ __('ops.devices.mac_missing') }}
                                @endif
                                · {{ $device->tenant?->name ?? __('ops.devices.no_tenant') }}
                                @if ($device->sn_scanned_at)
                                    · {{ __('ops.devices.scanned_at') }} {{ $device->sn_scanned_at->format('d-m-Y H:i') }}
                                @endif
                            </p>
                            @include('livewire.ops.device-placement', ['placement' => $placements[$device->id] ?? null])
                        </div>
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="openEdit({{ $device->id }})">
                            {{ __('ops.devices.edit') }}
                        </button>
                    </div>
                @endforeach
            </div>
            <div>
                {{ $devices->links() }}
            </div>
        @endif
    </div>

    @if ($showRegister)
        <x-wp-modal closeMethod="closeRegister" aria-labelledby="ops-register-title">
            <form wire:submit="register" class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <div class="wp-stack-tight">
                        <h2 id="ops-register-title" class="wp-section-title">{{ __('ops.devices.register_title') }}</h2>
                        <p class="wp-muted wp-text-sm">{{ __('ops.devices.register_hint') }}</p>
                    </div>
                    <x-wp-modal-close wire:click="closeRegister" />
                </div>
                <div class="wp-modal-body wp-stack">
                    <div>
                        <label class="wp-label" for="ops-reg-sn">{{ __('ops.fields.serial_number') }}</label>
                        <input id="ops-reg-sn" type="text" class="wp-input" wire:model="serial_number" autocomplete="off">
                        @error('serial_number') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-cluster wp-cluster--wrap">
                        <div class="wp-grow">
                            <label class="wp-label" for="ops-reg-brand">{{ __('ops.fields.brand') }}</label>
                            <input id="ops-reg-brand" type="text" class="wp-input" wire:model="brand" autocomplete="off">
                        </div>
                        <div class="wp-grow">
                            <label class="wp-label" for="ops-reg-model">{{ __('ops.fields.model') }}</label>
                            <input id="ops-reg-model" type="text" class="wp-input" wire:model="model" autocomplete="off">
                        </div>
                    </div>
                    <div class="wp-cluster wp-cluster--wrap">
                        <div class="wp-grow">
                            <label class="wp-label" for="ops-reg-type">{{ __('ops.fields.type') }}</label>
                            <select id="ops-reg-type" class="wp-input" wire:model="registerType">
                                @foreach ($types as $typeCase)
                                    <option value="{{ $typeCase->value }}">{{ __($typeCase->labelKey()) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="wp-grow">
                            <label class="wp-label" for="ops-reg-sku">{{ __('ops.fields.sku') }}</label>
                            <input id="ops-reg-sku" type="text" class="wp-input" wire:model="sku" autocomplete="off">
                        </div>
                    </div>
                    <div class="wp-cluster wp-cluster--wrap">
                        <div class="wp-grow">
                            <label class="wp-label" for="ops-reg-condition">{{ __('ops.fields.condition') }}</label>
                            <select id="ops-reg-condition" class="wp-input" wire:model="condition">
                                @foreach ($conditions as $conditionCase)
                                    <option value="{{ $conditionCase->value }}">{{ __($conditionCase->labelKey()) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="wp-grow">
                            <label class="wp-label" for="ops-reg-purchase">{{ __('ops.fields.purchase_ref') }}</label>
                            <input id="ops-reg-purchase" type="text" class="wp-input" wire:model="purchase_ref" autocomplete="off">
                        </div>
                    </div>
                    <div>
                        <label class="wp-label" for="ops-reg-notes">{{ __('ops.fields.notes') }}</label>
                        <textarea id="ops-reg-notes" class="wp-input" rows="2" wire:model="notes"></textarea>
                    </div>
                </div>
                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="closeRegister">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('ops.devices.register_submit') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif

    @if ($editDeviceId !== null)
        <x-wp-modal closeMethod="closeEdit" aria-labelledby="ops-edit-title">
            <form wire:submit="saveEdit" class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <div class="wp-stack-tight">
                        <h2 id="ops-edit-title" class="wp-section-title">{{ __('ops.devices.edit_title') }}</h2>
                    </div>
                    <x-wp-modal-close wire:click="closeEdit" />
                </div>
                <div class="wp-modal-body wp-stack">
                    <div class="wp-cluster wp-cluster--wrap">
                        <div class="wp-grow">
                            <label class="wp-label" for="ops-edit-brand">{{ __('ops.fields.brand') }}</label>
                            <input id="ops-edit-brand" type="text" class="wp-input" wire:model="editBrand" autocomplete="off">
                            @error('editBrand') <p class="wp-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="wp-grow">
                            <label class="wp-label" for="ops-edit-model">{{ __('ops.fields.model') }}</label>
                            <input id="ops-edit-model" type="text" class="wp-input" wire:model="editModel" autocomplete="off">
                        </div>
                    </div>
                    <div class="wp-cluster wp-cluster--wrap">
                        <div class="wp-grow">
                            <label class="wp-label" for="ops-edit-type">{{ __('ops.fields.type') }}</label>
                            <select id="ops-edit-type" class="wp-input" wire:model="editType">
                                @foreach ($types as $typeCase)
                                    <option value="{{ $typeCase->value }}">{{ __($typeCase->labelKey()) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="wp-grow">
                            <label class="wp-label" for="ops-edit-sku">{{ __('ops.fields.sku') }}</label>
                            <input id="ops-edit-sku" type="text" class="wp-input" wire:model="editSku" autocomplete="off">
                        </div>
                    </div>
                    <div class="wp-cluster wp-cluster--wrap">
                        <div class="wp-grow">
                            <label class="wp-label" for="ops-edit-status">{{ __('ops.fields.status') }}</label>
                            <select id="ops-edit-status" class="wp-input" wire:model="editStatus">
                                @foreach ($statuses as $statusCase)
                                    <option value="{{ $statusCase->value }}">{{ __($statusCase->labelKey()) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="wp-grow">
                            <label class="wp-label" for="ops-edit-condition">{{ __('ops.fields.condition') }}</label>
                            <select id="ops-edit-condition" class="wp-input" wire:model="editCondition">
                                @foreach ($conditions as $conditionCase)
                                    <option value="{{ $conditionCase->value }}">{{ __($conditionCase->labelKey()) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="wp-label" for="ops-edit-mac">{{ __('ops.fields.mac') }}</label>
                        <input id="ops-edit-mac" type="text" class="wp-input" wire:model="editMac" autocomplete="off"
                               @disabled($editMacLocked)>
                        <p class="wp-muted wp-text-sm">{{ __('ops.devices.mac_hint') }}</p>
                        @error('editMac') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="wp-label" for="ops-edit-tenant">{{ __('ops.fields.tenant') }}</label>
                        <select id="ops-edit-tenant" class="wp-input" wire:model="editTenantId">
                            <option value="">{{ __('ops.devices.no_tenant') }}</option>
                            @foreach ($tenants as $tenant)
                                <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                            @endforeach
                        </select>
                        @error('editTenantId') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="wp-label" for="ops-edit-purchase">{{ __('ops.fields.purchase_ref') }}</label>
                        <input id="ops-edit-purchase" type="text" class="wp-input" wire:model="editPurchaseRef" autocomplete="off">
                    </div>
                    <div>
                        <label class="wp-label" for="ops-edit-notes">{{ __('ops.fields.notes') }}</label>
                        <textarea id="ops-edit-notes" class="wp-input" rows="2" wire:model="editNotes"></textarea>
                    </div>
                </div>
                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="closeEdit">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('ops.devices.save') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif

    @if ($showFlashConfirm)
        <x-wp-modal closeMethod="cancelFlash" aria-labelledby="flash-device-title">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="flash-device-title" class="wp-h2">{{ __('ops.devices.flash') }}</h2>
                    <x-wp-modal-close wire:click="cancelFlash" />
                </div>
                <p>{{ __('ops.devices.flash_confirm', ['type' => $flashTargetType, 'mac' => $flashTargetMac]) }}</p>
                <div class="wp-cluster">
                    <button type="button" class="btn btn--surface" wire:click="cancelFlash">{{ __('ops.devices.flash_no') }}</button>
                    <button type="button" class="btn btn--primary" @click="confirmLocal()" :disabled="busy">
                        <span x-show="!busy">{{ __('ops.devices.flash_yes') }}</span>
                        <span x-show="busy" x-cloak>{{ __('ops.devices.flash_working') }}</span>
                    </button>
                </div>
            </div>
        </x-wp-modal>
    @endif
</div>
<script>
    function wpUsbFlash() {
        return {
            busy: false,
            raw: '',
            detectUrl: 'http://127.0.0.1/platform/usb-flash/detect',
            runUrl: 'http://127.0.0.1/platform/usb-flash/run',
            async detectLocal() {
                this.busy = true;
                try {
                    const data = await this.post(this.detectUrl, {});
                    if (data.status === 'found') {
                        this.raw = data.raw;
                        this.$wire.set('flashTargetType', data.type);
                        this.$wire.set('flashTargetMac', data.mac);
                        this.$wire.set('flashExpectedMac', data.raw);
                        this.$wire.set('showFlashConfirm', true);
                        this.$wire.set('flashMessage', null);
                        return;
                    }
                    this.raw = '';
                    this.$wire.set('showFlashConfirm', false);
                    this.$wire.set('flashMessage', data.message);
                    this.$wire.set('flashType', 'error');
                } catch (e) {
                    await this.$wire.detectFlash();
                } finally {
                    this.busy = false;
                }
            },
            async confirmLocal() {
                if (!this.raw) {
                    await this.$wire.confirmFlash();
                    return;
                }
                this.busy = true;
                try {
                    const data = await this.post(this.runUrl, { mac: this.raw });
                    this.raw = '';
                    this.$wire.set('flashMessage', data.message);
                    this.$wire.set('flashType', data.status === 'ok' ? 'success' : 'error');
                    this.$wire.set('showFlashConfirm', false);
                } catch (e) {
                    await this.$wire.confirmFlash();
                } finally {
                    this.busy = false;
                }
            },
            async post(url, body) {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(body),
                });
                if (!response.ok) {
                    throw new Error('usb-flash');
                }
                return response.json();
            },
        };
    }
</script>
