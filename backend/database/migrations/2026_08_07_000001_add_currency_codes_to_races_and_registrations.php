<?php

use App\Enums\CurrencyCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('races', 'currency_code')) {
            Schema::table('races', function (Blueprint $table): void {
                $table->string('currency_code', 3)->default(CurrencyCode::CNY->value)->after('config_version');
            });
        }

        if (! Schema::hasColumn('registrations', 'currency_code')) {
            Schema::table('registrations', function (Blueprint $table): void {
                $table->string('currency_code', 3)->default(CurrencyCode::CNY->value)->after('total_amount_cent');
            });
        }

        DB::table('races')->whereNull('currency_code')->update(['currency_code' => CurrencyCode::CNY->value]);
        DB::table('registrations')->whereNull('currency_code')->update(['currency_code' => CurrencyCode::CNY->value]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('registrations', 'currency_code')) {
            Schema::table('registrations', fn (Blueprint $table) => $table->dropColumn('currency_code'));
        }

        if (Schema::hasColumn('races', 'currency_code')) {
            Schema::table('races', fn (Blueprint $table) => $table->dropColumn('currency_code'));
        }
    }
};
