<?php

namespace Tests\Unit;

use App\Models\Project;
use PHPUnit\Framework\TestCase;

class ProjectBasisMonthTest extends TestCase
{
    public function test_current_month_project_uses_selected_month_as_basis_month(): void
    {
        $project = $this->makeProject('current');

        $this->assertSame('2026-07', $project->resolveBasisMonth('2026-07'));
    }

    public function test_next_month_project_uses_previous_month_as_basis_month(): void
    {
        $project = $this->makeProject('next');

        $this->assertSame('2026-06', $project->resolveBasisMonth('2026-07'));
    }

    public function test_next_month_project_handles_year_boundary(): void
    {
        $project = $this->makeProject('next');

        $this->assertSame('2025-12', $project->resolveBasisMonth('2026-01'));
    }

    public function test_next_month_project_resolves_processing_month_from_basis_month(): void
    {
        $project = $this->makeProject('next');

        $this->assertSame('2026-07', $project->resolveBasisProcessingMonth('2026-06'));
    }

    public function test_next_month_project_only_releases_previous_business_month(): void
    {
        $project = $this->makeProject('next');

        $this->assertTrue($project->isPayrollBusinessMonthAvailable('2026-06', '2026-07'));
        $this->assertFalse($project->isPayrollBusinessMonthAvailable('2026-07', '2026-07'));
    }

    public function test_business_month_must_be_within_project_dates(): void
    {
        $project = $this->makeProject('next', '2026-07-01', '2026-12-31');

        $this->assertFalse($project->isPayrollBusinessMonthAvailable('2026-06', '2026-07'));
        $this->assertTrue($project->isPayrollBusinessMonthAvailable('2026-07', '2026-08'));
        $this->assertFalse($project->isPayrollBusinessMonthAvailable('2027-01', '2027-02'));
    }

    private function makeProject(
        string $salaryPaymentMonth,
        ?string $startDate = null,
        ?string $endDate = null
    ): Project
    {
        $project = new Project();
        $project->setRawAttributes([
            'salary_payment_month' => $salaryPaymentMonth,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $project;
    }
}
