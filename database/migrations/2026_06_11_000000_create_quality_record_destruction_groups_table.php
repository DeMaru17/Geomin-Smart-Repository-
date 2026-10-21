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
        Schema::create('quality_record_destruction_groups', function (Blueprint $table) {
            $table->id();
            $table->string('bapr_number')->unique();
            $table->date('destruction_date')->index();
            $table->string('method', 255)->default('Dihapus dari server & backup');
            $table->unsignedBigInteger('destroyed_by_id')->nullable();
            $table->unsignedBigInteger('acknowledged_by_id')->nullable();
            $table->string('status')->default('open')->index(); // 'open' | 'finalized'
            $table->timestamp('finalized_at')->nullable();
            $table->json('snapshot_data')->nullable();
            $table->timestamps();
        });

        Schema::table('quality_records', function (Blueprint $table) {
            $table->foreignId('quality_record_destruction_group_id')
                ->nullable()
                ->after('status')
                ->constrained('quality_record_destruction_groups')
                ->nullOnDelete();
            $table->date('destruction_date')->nullable()->after('quality_record_destruction_group_id');
            $table->string('destruction_method', 255)->nullable()->after('destruction_date');
            $table->unsignedBigInteger('destruction_operator_id')->nullable()->after('destruction_method');
        });

        // Safely migrate existing records with status = 'Dimusnahkan'
        $timezone = config('app.timezone', 'Asia/Jakarta');
        $destroyedRecords = DB::table('quality_records')
            ->where('status', 'Dimusnahkan')
            ->orderBy('updated_at')
            ->get();

        $groupCache = [];
        $sequenceByYear = [];

        foreach ($destroyedRecords as $record) {
            $destroyedCarbon = Carbon::parse($record->updated_at ?? now())->setTimezone($timezone);
            $localDate = $destroyedCarbon->toDateString();
            $year = $destroyedCarbon->year;

            if (! isset($groupCache[$localDate])) {
                if (! isset($sequenceByYear[$year])) {
                    $sequenceByYear[$year] = 1;
                } else {
                    $sequenceByYear[$year]++;
                }

                $sequence = str_pad($sequenceByYear[$year], 3, '0', STR_PAD_LEFT);
                $day = $destroyedCarbon->format('d');
                $month = $destroyedCarbon->format('m');
                $baprNumber = "{$sequence}/LAB-BAPR/{$day}/{$month}/{$year}";

                $existing = DB::table('quality_record_destruction_groups')->where('bapr_number', $baprNumber)->exists();
                if ($existing) {
                    $baprNumber = "{$sequence}-MIG/LAB-BAPR/{$day}/{$month}/{$year}";
                }

                $groupId = DB::table('quality_record_destruction_groups')->insertGetId([
                    'bapr_number' => $baprNumber,
                    'destruction_date' => $localDate,
                    'method' => 'Dihapus dari server & backup',
                    'destroyed_by_id' => $record->uploader_id ?? null,
                    'status' => 'open',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $groupCache[$localDate] = $groupId;
            }

            DB::table('quality_records')
                ->where('id', $record->id)
                ->update([
                    'quality_record_destruction_group_id' => $groupCache[$localDate],
                    'destruction_date' => $localDate,
                    'destruction_method' => 'Dihapus dari server & backup',
                    'destruction_operator_id' => $record->uploader_id ?? null,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quality_records', function (Blueprint $table) {
            $table->dropForeign(['quality_record_destruction_group_id']);
            $table->dropColumn([
                'quality_record_destruction_group_id',
                'destruction_date',
                'destruction_method',
                'destruction_operator_id',
            ]);
        });

        Schema::dropIfExists('quality_record_destruction_groups');
    }
};
