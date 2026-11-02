<?php

namespace Database\Seeders;

use App\Enums\BusinessRole;
use App\Enums\DocumentType;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\Category;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Two businesses, so you can see isolation and switching.
     * Every account's password is "password".
     *
     * Kilimani Bakery: owner@ (Owner), manager@ (Manager), employee@ (Employee)
     * Savannah Logistics: hassan@ (Owner), owner@ (Employee)
     */
    public function run(): void
    {
        $owner = User::factory()->create(['name' => 'Demo Owner', 'email' => 'owner@documind.test']);
        $manager = User::factory()->create(['name' => 'Grace Wanjiku', 'email' => 'manager@documind.test']);
        $employee = User::factory()->create(['name' => 'Brian Otieno', 'email' => 'employee@documind.test']);
        $hassan = User::factory()->create(['name' => 'Hassan Ali', 'email' => 'hassan@documind.test']);

        $bakery = Business::factory()->for($owner, 'owner')->create(['name' => 'Kilimani Bakery Ltd']);
        $bakery->members()->attach([
            $manager->id => ['role' => BusinessRole::Manager->value],
            $employee->id => ['role' => BusinessRole::Employee->value],
        ]);
        $manager->switchBusiness($bakery);
        $employee->switchBusiness($bakery);

        $logistics = Business::factory()->for($hassan, 'owner')->create(['name' => 'Savannah Logistics Ltd']);
        $logistics->members()->attach($owner->id, ['role' => BusinessRole::Employee->value]);

        $this->seedRecords($bakery, uploader: $manager, assignee: $employee);
        $this->seedRecords($logistics, uploader: $hassan, assignee: $owner);

        BusinessInvitation::create([
            'business_id' => $bakery->id,
            'invited_by' => $owner->id,
            'email' => 'accountant@documind.test',
            'role' => BusinessRole::Employee,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);
    }

    private function seedRecords(Business $business, User $uploader, User $assignee): void
    {
        $categories = collect([
            'Supplies' => 'amber',
            'Utilities' => 'sky',
            'Rent' => 'rose',
            'Transport' => 'emerald',
        ])->map(fn (string $color, string $name): Category => Category::factory()->for($business)->create([
            'name' => $name,
            'color' => $color,
        ]));

        $invoiceFactories = [
            Invoice::factory(),
            Invoice::factory(),
            Invoice::factory()->overdue(),
            Invoice::factory()->approved(),
            Invoice::factory()->paid(),
            Invoice::factory()->paid(),
        ];

        foreach ($invoiceFactories as $invoiceFactory) {
            $invoice = $invoiceFactory->for($business)->create();

            $document = Document::factory()->for($business)->create([
                'type' => DocumentType::Invoice,
                'category_id' => $categories->random()->id,
                'uploaded_by' => $uploader->id,
                'original_name' => Str::slug($invoice->supplier_name).'-'.Str::lower($invoice->invoice_number).'.pdf',
            ]);

            $invoice->document()->associate($document)->save();
        }

        Document::factory()->count(2)->for($business)->create([
            'type' => DocumentType::Receipt,
            'category_id' => $categories['Transport']->id,
            'uploaded_by' => $uploader->id,
        ]);
        Document::factory()->reviewRequired()->for($business)->create(['uploaded_by' => $uploader->id]);
        Document::factory()->pending()->for($business)->create(['uploaded_by' => $uploader->id]);

        $overdue = $business->invoices()->overdue()->first();

        Task::factory()->for($business)->for($overdue, 'taskable')->create([
            'title' => "Pay overdue invoice {$overdue->invoice_number}",
            'created_by' => $uploader->id,
            'assigned_to' => $assignee->id,
            'due_date' => now()->addDays(2),
        ]);
        Task::factory()->for($business)->create([
            'title' => 'Review documents flagged for review',
            'created_by' => $uploader->id,
            'assigned_to' => $assignee->id,
        ]);
        Task::factory()->done()->for($business)->create(['assigned_to' => $assignee->id]);

        AuditLog::create([
            'business_id' => $business->id,
            'user_id' => $business->owner_id,
            'action' => 'business.created',
            'auditable_type' => $business->getMorphClass(),
            'auditable_id' => $business->id,
        ]);
    }
}
