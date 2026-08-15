@extends('layouts.app')

@section('title', __('System Settings'))

@section('content')
<div class="container-xl">
    @include('partials.page_header', [
        'title'    => __('System Settings'),
        'subtitle' => __('Branding & application identity'),
        'icon'     => 'ti-settings',
    ])

    @if(session('success'))
        <div class="alert alert-success alert-dismissible mb-4">
            <i class="ti ti-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-lg-7">
            <form action="{{ route('settings.system.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title"><i class="ti ti-type me-2"></i>{{ __('Application Name') }}</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-0">
                            <label class="form-label">{{ __('App name') }} <span class="text-red">*</span></label>
                            <input type="text" name="app_name" class="form-control @error('app_name') is-invalid @enderror"
                                   value="{{ old('app_name', $appName) }}" required maxlength="80"
                                   placeholder="e.g. VSLA Manager">
                            @error('app_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">{{ __('Shown in the browser tab, login screen and sidebar.') }}</small>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title"><i class="ti ti-photo me-2"></i>{{ __('Logo') }}</h3>
                    </div>
                    <div class="card-body">
                        @if($appLogo)
                            <div class="mb-3 d-flex align-items-center gap-3">
                                <img src="{{ $appLogo }}" alt="Current logo"
                                     style="height:64px;max-width:200px;object-fit:contain;border:1px solid #dee2e6;border-radius:6px;padding:6px;background:#fff;">
                                <div>
                                    <div class="text-muted small mb-1">{{ __('Current logo') }}</div>
                                    <label class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" name="remove_logo" value="1">
                                        <span class="form-check-label text-danger">{{ __('Remove logo') }}</span>
                                    </label>
                                </div>
                            </div>
                        @else
                            <div class="mb-3 text-muted small">
                                <i class="ti ti-photo-off me-1"></i>{{ __('No logo uploaded — the default coin icon is shown.') }}
                            </div>
                        @endif

                        <div>
                            <label class="form-label">{{ $appLogo ? __('Replace logo') : __('Upload logo') }}</label>
                            <input type="file" name="app_logo" class="form-control @error('app_logo') is-invalid @enderror"
                                   accept="image/*">
                            @error('app_logo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">{{ __('PNG, JPG or WebP · max 2 MB · recommended height 48–64 px') }}</small>
                        </div>
                    </div>
                </div>

                <div class="card mb-4 border-warning">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="ti ti-construction me-2 text-warning"></i>{{ __('Under construction mode') }}
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" name="under_construction"
                                   value="1" id="under-construction-toggle"
                                   @checked(old('under_construction', $underConstruction))>
                            <label class="form-check-label fw-semibold" for="under-construction-toggle">
                                {{ __('Show the under construction page') }}
                            </label>
                            <div class="form-hint">
                                {{ __('Visitors and regular users will see the construction page. Super admins can continue using the application.') }}
                            </div>
                        </div>
                        <div>
                            <label class="form-label">{{ __('Message for visitors') }} <span class="text-red">*</span></label>
                            <textarea name="under_construction_message" rows="4"
                                      class="form-control @error('under_construction_message') is-invalid @enderror"
                                      maxlength="1000" required>{{ old('under_construction_message', $underConstructionMessage) }}</textarea>
                            @error('under_construction_message')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">{{ __('This message appears beneath the construction notice.') }}</small>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i>{{ __('Save settings') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
