<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const departureAirportId = ref(null);
const arrivalAirportId = ref(null);
const aircraftId = ref(null);
const departureDate = ref('');
const departureTime = ref('');

const result = ref(null);
const calculating = ref(false);
const errorMessage = ref('');

const sameAirportSelected = computed(
    () =>
        departureAirportId.value !== null &&
        arrivalAirportId.value !== null &&
        departureAirportId.value === arrivalAirportId.value
);

const readyToCalculate = computed(
    () =>
        departureAirportId.value !== null &&
        arrivalAirportId.value !== null &&
        aircraftId.value !== null &&
        departureDate.value !== '' &&
        departureTime.value !== '' &&
        !sameAirportSelected.value
);

let debounceTimer = null;

watch([departureAirportId, arrivalAirportId, aircraftId, departureDate, departureTime], () => {
    result.value = null;
    errorMessage.value = '';
    clearTimeout(debounceTimer);

    if (!readyToCalculate.value) {
        return;
    }

    debounceTimer = setTimeout(runCalculation, 250);
});

const runCalculation = async () => {
    calculating.value = true;
    errorMessage.value = '';

    try {
        const response = await window.axios.post(route('flight-calculator.calculate'), {
            departure_airport_id: departureAirportId.value,
            arrival_airport_id: arrivalAirportId.value,
            aircraft_speed_reference_id: aircraftId.value,
            departure_date: departureDate.value,
            departure_time: departureTime.value,
        });

        result.value = response.data;
    } catch (error) {
        result.value = null;
        errorMessage.value =
            error.response?.data?.message ?? 'Could not calculate this flight. Please check the fields above.';
    } finally {
        calculating.value = false;
    }
};

const airportLabel = (airport) =>
    `${airport.name} (${airport.icao_code}${airport.iata_code ? '/' + airport.iata_code : ''})`;

const aircraftLabel = (aircraft) => aircraft.type_name;
</script>

<template>
    <Head title="Flight Calculator" />

    <AdminLayout title="Flight Calculator">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="card p-6">
                <h2 class="text-sm font-semibold text-gray-900">Flight Details</h2>

                <div class="mt-4 space-y-6">
                    <div>
                        <InputLabel for="departure_airport" value="Departure Airport" />
                        <SearchableSelect
                            id="departure_airport"
                            v-model="departureAirportId"
                            :search-url="route('airports.search')"
                            :option-label="airportLabel"
                            placeholder="Search by name, ICAO or IATA…"
                            class="mt-1"
                        >
                            <template #option="{ option }">
                                <span class="font-medium text-gray-900">{{ option.name }}</span>
                                <span class="ml-1 text-gray-500"
                                    >({{ option.icao_code }}{{ option.iata_code ? '/' + option.iata_code : '' }})</span
                                >
                            </template>
                        </SearchableSelect>
                    </div>

                    <div>
                        <InputLabel for="arrival_airport" value="Arrival Airport" />
                        <SearchableSelect
                            id="arrival_airport"
                            v-model="arrivalAirportId"
                            :search-url="route('airports.search')"
                            :option-label="airportLabel"
                            placeholder="Search by name, ICAO or IATA…"
                            class="mt-1"
                        >
                            <template #option="{ option }">
                                <span class="font-medium text-gray-900">{{ option.name }}</span>
                                <span class="ml-1 text-gray-500"
                                    >({{ option.icao_code }}{{ option.iata_code ? '/' + option.iata_code : '' }})</span
                                >
                            </template>
                        </SearchableSelect>
                        <p v-if="sameAirportSelected" class="mt-2 text-sm text-red-600">
                            Departure and arrival airports must be different.
                        </p>
                    </div>

                    <div>
                        <InputLabel for="aircraft_type" value="Aircraft Type" />
                        <SearchableSelect
                            id="aircraft_type"
                            v-model="aircraftId"
                            :search-url="route('aircraft-speed-references.search')"
                            :option-label="aircraftLabel"
                            placeholder="Search aircraft type…"
                            class="mt-1"
                        >
                            <template #option="{ option }">
                                <span class="text-gray-900">{{ option.type_name }}</span>
                            </template>
                        </SearchableSelect>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="departure_date" value="Departure Date" />
                            <TextInput
                                id="departure_date"
                                v-model="departureDate"
                                type="date"
                                class="mt-1 block w-full"
                            />
                        </div>

                        <div>
                            <InputLabel for="departure_time" value="Departure Time" />
                            <TextInput
                                id="departure_time"
                                v-model="departureTime"
                                type="time"
                                lang="en-GB"
                                class="mt-1 block w-full"
                            />
                        </div>
                    </div>

                    <p class="text-xs text-gray-500">
                        Departure date/time is entered as local time at the departure airport.
                    </p>
                </div>
            </div>

            <div class="card p-6">
                <h2 class="text-sm font-semibold text-gray-900">Result</h2>

                <div v-if="errorMessage" class="mt-4 text-sm text-red-600">
                    {{ errorMessage }}
                </div>

                <div v-else-if="calculating" class="mt-4 text-sm text-gray-500">Calculating…</div>

                <div v-else-if="result" class="mt-4 space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="rounded-lg bg-gray-50 p-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Distance</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900">
                                {{ result.distance_nautical_miles.toLocaleString() }} nm
                            </p>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Flight Duration</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900">
                                {{ result.duration_formatted }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                                Departure — {{ result.departure.airport.name }} local time
                            </p>
                            <p class="mt-1 text-base font-medium text-gray-900">
                                {{ result.departure.formatted }}
                            </p>
                            <p class="text-sm text-gray-500">
                                {{ result.departure.timezone }} ({{ result.departure.utc_offset }})
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                                Arrival — {{ result.arrival.airport.name }} local time
                            </p>
                            <p class="mt-1 text-base font-medium text-gray-900">
                                {{ result.arrival.formatted }}
                            </p>
                            <p class="text-sm text-gray-500">
                                {{ result.arrival.timezone }} ({{ result.arrival.utc_offset }})
                            </p>
                        </div>
                    </div>
                </div>

                <div v-else class="mt-4 text-sm text-gray-500">
                    Fill in departure and arrival airports, aircraft type, and departure date/time to see the
                    calculated flight.
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
