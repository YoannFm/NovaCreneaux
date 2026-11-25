<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Slot;
use App\Models\User;
use App\Services\PdfRenderer;
use App\Support\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class ReservationPdfExportTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private const DATE = '2026-12-01';

    private function slot(string $resource, string $name, string $start): Slot
    {
        $resource = Resource::firstOrCreate(['name' => $resource], ['open_time' => '00:00', 'close_time' => '23:59', 'closed_days' => []]);

        return Slot::create(['resource_id' => $resource->id, 'name' => $name, 'start_time' => $start, 'end_time' => '23:00', 'max_capacity' => 30]);
    }

    private function book(Slot $slot, string $name, string $date = self::DATE): void
    {
        $user = User::factory()->create(['name' => $name, 'classe' => '2nde A', 'role_id' => Role::defaultUser()->id]);
        Reservation::create(['user_id' => $user->id, 'slot_id' => $slot->id, 'reservation_date' => $date]);
    }

    /** Le PDF est remplace par le HTML qui l'aurait produit : on peut lire le contenu. */
    private function renderHtmlInsteadOfPdf(): void
    {
        $this->app->instance(PdfRenderer::class, new class extends PdfRenderer {
            public function render(string $view, array $data = [], string $orientation = 'portrait'): string
            {
                return view($view, $data)->render();
            }
        });
    }

    private function export(array $query = [])
    {
        return $this->get(route('admin.reservations.index', $query + ['date' => self::DATE, 'export' => 'pdf']));
    }

    public function test_the_page_offers_the_pdf_export_with_the_current_filters(): void
    {
        $this->actingAs($this->admin())->get(route('admin.reservations.index', ['date' => self::DATE]))->assertOk()
            ->assertSee('export=pdf', false)->assertSee('date=' . self::DATE, false);
    }

    public function test_a_real_pdf_file_is_downloaded_and_the_export_is_logged(): void
    {
        $this->book($this->slot('CDI', 'M1', '08:00'), 'Léa Martin');

        $response = $this->actingAs($this->admin())->export()->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="reservations_' . self::DATE . '.pdf"');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertTrue(ActivityLog::where('action', 'Export PDF des réservations')->where('context', self::DATE)->exists());
    }

    public function test_the_listing_holds_only_the_chosen_day_grouped_by_resource_and_sorted_by_time_then_name(): void
    {
        $this->renderHtmlInsteadOfPdf();
        $m2 = $this->slot('CDI', 'M2', '09:00');
        $m1 = $this->slot('CDI', 'M1', '08:00');
        $pc = $this->slot('Postes informatiques', 'Récré', '10:00');
        $this->book($m2, 'Noah Thomas');
        $this->book($m1, 'Zoé Moreau');
        $this->book($m2, 'Élodie Durand');
        $this->book($pc, 'Hugo Bernard');
        $this->book($m1, 'Autre Jour', '2026-12-02');

        $html = $this->actingAs($this->admin())->export()->assertOk()->getContent();

        $this->assertStringContainsString('Réservations du mardi 1 décembre 2026', $html);
        $this->assertStringContainsString('Toutes les ressources - 4 réservations', $html);
        $this->assertStringNotContainsString('Autre Jour', $html);
        $this->assertMatchesRegularExpression('/CDI \(3\).*Zoé Moreau.*Élodie Durand.*Noah Thomas.*Postes informatiques \(1\).*Hugo Bernard/s', $html);
    }

    public function test_the_page_offers_the_three_layouts(): void
    {
        $page = $this->actingAs($this->admin())->get(route('admin.reservations.index', ['date' => self::DATE]))->assertOk();

        foreach (['list' => 'Une seule liste', 'slots' => 'Un bloc par créneau', 'pages' => 'Une page par créneau'] as $layout => $label) {
            $page->assertSee("layout={$layout}", false)->assertSee($label);
        }
    }

    public function test_slots_can_be_separated_in_blocks_or_one_page_each(): void
    {
        $this->renderHtmlInsteadOfPdf();
        $m2 = $this->slot('CDI', 'M2', '09:00');
        $m1 = $this->slot('CDI', 'M1', '08:00');
        $this->book($m2, 'Noah Thomas');
        $this->book($m1, 'Zoé Moreau');
        $this->book($m2, 'Élodie Durand');
        $this->actingAs($this->admin());

        $blocks = $this->export(['layout' => 'slots'])->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/1 \/ 30 places.*CDI - 08:00 - 23:00 - M1.*Zoé Moreau.*2 \/ 30 places.*CDI - 09:00 - 23:00 - M2.*Élodie Durand.*Noah Thomas/s',
            preg_replace('/\s+/', ' ', $blocks));
        $this->assertSame(2, substr_count($blocks, 'class="slot '));
        $this->assertStringNotContainsString('slot-page"', $blocks);
        $this->assertStringNotContainsString('Présent', $blocks);

        $pages = $this->export(['layout' => 'pages'])->assertOk()->getContent();
        $this->assertSame(1, substr_count($pages, 'slot-page"'), 'chaque creneau apres le premier commence une page');
        $this->assertSame(2, substr_count($pages, '<th style="width:11%;">Présent</th>'));
        $this->assertStringContainsString('Mardi 1 décembre 2026', $pages);
        $this->assertTrue(ActivityLog::where('action', 'Export PDF des réservations')->where('context', self::DATE . ', une page par créneau')->exists());

        $this->export(['layout' => 'autre'])->assertSessionHasErrors('layout');
    }

    public function test_the_resource_filter_applies_and_names_are_escaped(): void
    {
        $this->renderHtmlInsteadOfPdf();
        $cdi = $this->slot('CDI', 'M1', '08:00');
        $this->book($cdi, '<b>Gras</b>');
        $this->book($this->slot('Postes informatiques', 'Récré', '10:00'), 'Hugo Bernard');

        $html = $this->actingAs($this->admin())->export(['resource_id' => $cdi->resource_id])->assertOk()->getContent();

        $this->assertStringContainsString('CDI - 1 réservation', $html);
        $this->assertStringContainsString('&lt;b&gt;Gras&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('Hugo Bernard', $html);
    }

    public function test_an_invalid_date_is_refused_instead_of_breaking_the_page(): void
    {
        $this->actingAs($this->admin())
            ->from(route('admin.reservations.index'))
            ->get(route('admin.reservations.index', ['date' => '2026-12-01"', 'export' => 'pdf']))
            ->assertRedirect(route('admin.reservations.index'))->assertSessionHasErrors('date');
    }

    public function test_staff_managing_reservations_can_export_but_students_cannot(): void
    {
        $this->actingAs($this->staff([Permission::RESERVATIONS]))->export()->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->student())->export()->assertRedirect('/');
    }
}
