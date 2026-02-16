<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (EmailTemplate::defaults() as $template) {
            EmailTemplate::firstOrCreate(
                ['slug' => $template['slug']],
                $template
            );
        }
    }
}
