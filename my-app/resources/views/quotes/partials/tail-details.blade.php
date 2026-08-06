{{-- Amenity badges + seats/cabin line for a matched Tail. Shared between
     the two-column layout (tail has photos) and the plain fallback (tail
     matched but has no photos on file) so the two don't drift apart. --}}
@if (count($tail['amenities']) > 0)
    <div class="amenities">
        <div class="detail-label">Amenities</div>
        @foreach ($tail['amenities'] as $amenity)
            <span class="amenity-badge">{{ $amenity }}</span>
        @endforeach
    </div>
@endif

@if ($tail['seats'] !== null || $tail['cabin_summary'])
    <div class="cabin-block">
        @if ($tail['seats'] !== null)
            <table class="detail-row">
                <tr>
                    <td class="detail-label-inline">Seats –</td>
                    <td class="cabin-seats">{{ $tail['seats'] }}</td>
                </tr>
            </table>
        @endif
        @if ($tail['cabin_summary'])
            <table class="detail-row cabin-size-row">
                <tr>
                    <td class="detail-label-inline">Cabin size –</td>
                    <td class="cabin-summary">{{ $tail['cabin_summary'] }}</td>
                </tr>
            </table>
        @endif
    </div>
@endif
