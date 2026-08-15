<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('username', 80)->nullable()->after('name');
            });
        }

        // Give existing accounts a stable username before adding the unique
        // index. The email prefix is the least surprising starting point.
        $users = DB::table('users')->whereNull('username')->orderBy('id')->get(['id', 'email']);
        foreach ($users as $user) {
            $base = Str::of(Str::before((string) $user->email, '@'))
                ->lower()
                ->replaceMatches('/[^a-z0-9._-]+/', '')
                ->trim('._-')
                ->substr(0, 70)
                ->value();
            $base = $base !== '' ? $base : 'user';

            $username = $base;
            $suffix = 1;
            while (DB::table('users')->where('username', $username)->exists()) {
                $username = substr($base, 0, 70).'-'.$suffix++;
            }

            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('username', 'users_username_unique');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_username_unique');
                $table->dropColumn('username');
            });
        }
    }
};