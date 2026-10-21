<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_destruction_groups', function (Blueprint $table) {
            $table->id();
            $table->string('bapd_number')->unique();
            $table->date('approval_date')->index();
            $table->string('status')->default('open')->index(); // 'open' | 'finalized'
            $table->timestamp('finalized_at')->nullable();
            $table->json('snapshot_data')->nullable();
            $table->timestamps();
        });

        Schema::table('document_destructions', function (Blueprint $table) {
            $table->foreignId('document_destruction_group_id')
                ->nullable()
                ->after('bapd_number')
                ->constrained('document_destruction_groups')
                ->nullOnDelete();
        });

        // Safe forward migration for existing approved destruction records
        $timezone = config('app.timezone', 'Asia/Jakarta');
        $approvedDestructions = DB::table('document_destructions')
            ->where('status', 'Disetujui')
            ->whereNotNull('approved_at')
            ->orderBy('approved_at')
            ->get();

        $groupCache = [];
        $sequenceByYear = [];

        foreach ($approvedDestructions as $record) {
            $approvedCarbon = Carbon::parse($record->approved_at)->setTimezone($timezone);
            $localDate = $approvedCarbon->toDateString();
            $year = $approvedCarbon->year;

            if (! isset($groupCache[$localDate])) {
                if (! isset($sequenceByYear[$year])) {
                    $sequenceByYear[$year] = 1;
                } else {
                    $sequenceByYear[$year]++;
                }

                $sequence = str_pad($sequenceByYear[$year], 3, '0', STR_PAD_LEFT);
                $day = $approvedCarbon->format('d');
                $month = $approvedCarbon->format('m');
                $bapdNumber = "{$sequence}/LAB-BAPD/{$day}/{$month}/{$year}";

                // Ensure bapdNumber is unique
                $existing = DB::table('document_destruction_groups')->where('bapd_number', $bapdNumber)->exists();
                if ($existing) {
                    $bapdNumber = "{$sequence}-MIG/LAB-BAPD/{$day}/{$month}/{$year}";
                }

                $groupId = DB::table('document_destruction_groups')->insertGetId([
                    'bapd_number' => $bapdNumber,
                    'approval_date' => $localDate,
                    'status' => 'open',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $groupCache[$localDate] = $groupId;
            }

            DB::table('document_destructions')
                ->where('id', $record->id)
                ->update(['document_destruction_group_id' => $groupCache[$localDate]]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_destructions', function (Blueprint $table) {
            $table->dropForeign(['document_destruction_group_id']);
            $table->dropColumn('document_destruction_group_id');
        });

        Schema::dropIfExists('document_destruction_groups');
    }
};
