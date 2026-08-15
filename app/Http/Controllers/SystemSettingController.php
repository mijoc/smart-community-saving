<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SystemSettingController extends Controller
{
    public function edit()
    {
        return view('settings.system', [
            'appName' => SystemSetting::get('app_name', config('app.name')),
            'appLogo' => SystemSetting::publicUrl(SystemSetting::get('app_logo')),
            'underConstruction' => filter_var(SystemSetting::get('under_construction_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'underConstructionMessage' => SystemSetting::get(
                'under_construction_message',
                'We are putting the finishing touches on your experience. Please check back soon.'
            ),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'app_name' => 'required|string|max:80',
            'app_logo' => 'nullable|mimes:jpg,jpeg,png,webp|max:2048',
            'under_construction_message' => 'required|string|max:1000',
        ]);

        SystemSetting::set('app_name', trim($request->app_name));
        SystemSetting::set('under_construction_enabled', $request->boolean('under_construction'));
        SystemSetting::set('under_construction_message', trim($request->input('under_construction_message')));

        if ($request->hasFile('app_logo')) {
            $old = SystemSetting::get('app_logo');
            if ($old) {
                $oldPath = ltrim(parse_url($old, PHP_URL_PATH), '/');
                $rel = str_replace('storage/', '', $oldPath);
                Storage::disk('public')->delete($rel);
            }

            $path = $request->file('app_logo')->store('system', 'public');
            // Keep the stored value portable between localhost, Replit and
            // cPanel. The display helper generates the current full URL.
            SystemSetting::set('app_logo', '/storage/'.$path);
        }

        if ($request->boolean('remove_logo') && ! $request->hasFile('app_logo')) {
            $old = SystemSetting::get('app_logo');
            if ($old) {
                $rel = str_replace('/storage/', '', parse_url($old, PHP_URL_PATH));
                Storage::disk('public')->delete($rel);
            }
            SystemSetting::set('app_logo', null);
        }

        return back()->with('success', 'System settings saved.');
    }
}
