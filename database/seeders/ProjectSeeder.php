<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 创建测试项目
        $projects = [
            [
                'name' => '示例项目A',
                'code' => 'PROJ-A-001',
                'description' => '这是一个示例项目A',
                'status' => 'active',
                'social_security_location' => '北京',
                'requires_attendance' => true,
                'delivery_frequency' => 'monthly',
                'delivery_method' => 'electronic',
            ],
            [
                'name' => '示例项目B',
                'code' => 'PROJ-B-001',
                'description' => '这是一个示例项目B',
                'status' => 'active',
                'social_security_location' => '上海',
                'requires_attendance' => true,
                'delivery_frequency' => 'monthly',
                'delivery_method' => 'express',
            ],
            [
                'name' => '示例项目C',
                'code' => 'PROJ-C-001',
                'description' => '这是一个示例项目C',
                'status' => 'active',
                'social_security_location' => '深圳',
                'requires_attendance' => false,
                'delivery_frequency' => 'quarterly',
                'delivery_method' => 'electronic',
            ],
        ];

        foreach ($projects as $projectData) {
            Project::create($projectData);
        }

        echo "已创建 " . count($projects) . " 个测试项目\n";
        echo "\n项目列表:\n";
        foreach (Project::all() as $project) {
            echo "  ID: {$project->id} - {$project->name} ({$project->code})\n";
        }
        echo "\n";
    }
}

