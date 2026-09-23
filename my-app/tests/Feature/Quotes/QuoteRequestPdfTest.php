<?php

namespace Tests\Feature\Quotes;

use App\Models\Client;
use App\Models\QuoteOffer;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression cover for "Generate PDF" crashing on a selected offer with no
 * price yet — a manual quote's auto-created reference offer starts with
 * offered_price/offered_currency both null (see QuoteOffer::forTail()),
 * meant to be filled in on the offer page afterward, but nothing stops it
 * from being checked "selected" before that happens. quotes.pdf's
 * $formatAmount closure is typed (float $amount, string $currency), so a
 * null offered_currency there used to be a fatal TypeError, not a
 * validation failure.
 */
class QuoteRequestPdfTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    private function quoteRequestWithSelectedOffer(array $offerOverrides = []): QuoteRequest
    {
        $client = Client::create(['company_name' => 'Acme Aviation']);

        $quoteRequest = QuoteRequest::create([
            'avinode_trip_id' => null,
            'client_id' => $client->id,
            'status' => 'offers_received',
            'reference_label' => 'MANUAL-TEST-01',
        ]);

        QuoteOffer::create(array_merge([
            'quote_request_id' => $quoteRequest->id,
            'operator_name' => 'Test Air',
            'aircraft_type' => 'Citation X',
            'aircraft_registration' => 'N123AB',
            'offered_price' => null,
            'offered_currency' => null,
            'raw_email_body' => '',
            'selected' => true,
            'source' => 'manual',
        ], $offerOverrides));

        return $quoteRequest;
    }

    public function test_generating_pdf_is_blocked_with_a_clear_message_when_selected_offer_has_no_price(): void
    {
        $this->actingAsUser();
        $quoteRequest = $this->quoteRequestWithSelectedOffer();

        $response = $this->get(route('quote-requests.pdf', $quoteRequest->id));

        $response->assertStatus(400);
        $response->assertSeeText(
            'Offer for Citation X is missing a price — please add one before generating the PDF.'
        );
    }

    public function test_generating_pdf_is_blocked_when_offer_has_price_but_no_currency(): void
    {
        $this->actingAsUser();
        $quoteRequest = $this->quoteRequestWithSelectedOffer(['offered_price' => 12000]);

        $response = $this->get(route('quote-requests.pdf', $quoteRequest->id));

        $response->assertStatus(400);
        $response->assertSeeText(
            'Offer for Citation X is missing a price — please add one before generating the PDF.'
        );
    }

    public function test_generating_pdf_succeeds_once_price_and_currency_are_set(): void
    {
        $this->actingAsUser();
        $quoteRequest = $this->quoteRequestWithSelectedOffer([
            'offered_price' => 12000,
            'offered_currency' => 'EUR',
        ]);

        $response = $this->get(route('quote-requests.pdf', $quoteRequest->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_unselected_offer_missing_a_price_does_not_block_generation(): void
    {
        $this->actingAsUser();
        $quoteRequest = $this->quoteRequestWithSelectedOffer([
            'offered_price' => 12000,
            'offered_currency' => 'EUR',
        ]);

        // A second, unselected offer with no price at all -- only what's
        // actually selected should ever be judged.
        QuoteOffer::create([
            'quote_request_id' => $quoteRequest->id,
            'operator_name' => 'Other Air',
            'aircraft_type' => 'Phenom 300',
            'raw_email_body' => '',
            'offered_price' => null,
            'offered_currency' => null,
            'selected' => false,
            'source' => 'manual',
        ]);

        $response = $this->get(route('quote-requests.pdf', $quoteRequest->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
