<?php

// database/seeders/AdminSeeder.php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates exactly one production admin account, safe to run anywhere
 * (including production) — unlike DevSeeder, which refuses to run
 * outside local/dev on purpose.
 *
 * Run once after first deploy:
 *   php artisan db:seed --class=Database\\Seeders\\AdminSeeder
 *
 * Behavior:
 * - Idempotent: if an admin with this student_id already exists, it does
 *   nothing and tells you so — running it twice never resets the password.
 * - Generates a random 16-character password and prints it to the console
 *   ONCE. Nothing sensitive is written to storage or logs beyond this run.
 * - Forces must_change_password = true, so the random password is only
 *   ever used for the first login before the admin sets their own.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // DevSeeder creates this too, but DevSeeder refuses to run in
        // production — so without this, organizations stays empty in any
        // real deployment. OfficerAppointmentController::store() reads
        // Organization::first() and writes it into officer_positions.
        // organization_id, which is a required (non-nullable) foreign key
        // — so a missing Organization row crashes every officer
        // appointment with a 500. firstOrCreate keeps this safe to run
        // alongside the idempotent admin-creation logic below.
        Organization::firstOrCreate(
            ['name' => 'Student Government Organization'],
            [
                'description'   => 'Philippine Advent College — Student Government Organization',
                'logo'          => null,
                'department'    => 'All Departments',
                'academic_year' => now()->month >= 6
                    ? now()->year . '-' . (now()->year + 1)
                    : (now()->year - 1) . '-' . now()->year,
                'is_active'     => true,
            ]
        );

        $studentId = env('ADMIN_STUDENT_ID', 'A0000000001');
        $email     = env('ADMIN_EMAIL', 'admin@soms.local');

        $existing = User::where('student_id', $studentId)->first();

        if ($existing) {
            $this->command->warn("Admin already exists (student_id: {$studentId}). No changes made.");
            $this->command->warn('To reset the password, do it through the app (forgot-password flow) rather than re-seeding.');
            return;
        }

        $plainPassword = Str::password(16);

        $admin = User::create([
            'student_id'           => $studentId,
            'name'                 => env('ADMIN_NAME', 'System Administrator'),
            'email'                => $email,
            'password'             => Hash::make($plainPassword),
            'role'                 => 'admin',
            'department'           => 'SGO',
            'program'              => 'N/A',
            'level'                => 'N/A',
            'is_approved'          => true,
            'must_change_password' => true,
        ]);

        ActivityLog::record($admin->id, 'admin_seeded', User::class, $admin->id, [
            'student_id' => $admin->student_id,
        ]);

        $this->command->info('Admin account created.');
        $this->command->info("student_id: {$studentId}");
        $this->command->info("email:      {$email}");
        $this->command->info("password:   {$plainPassword}");
        $this->command->warn('Copy this password now — it is not stored anywhere and will not be shown again.');
        $this->command->warn('You will be required to change it on first login.');
    }
}
