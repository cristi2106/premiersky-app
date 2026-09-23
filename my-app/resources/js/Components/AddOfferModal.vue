<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    quoteRequestId: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(['close']);

const form = useForm({
    tail_id: null,
    offered_price: '',
    offered_currency: 'EUR',
    commission_type: null,
    commission_value: '',
});

// The selected Tail's own record, for the read-only Operator/Type/Year/Max
// PAX block below — set by SearchableSelect's @select, alongside
// form.tail_id itself. Kept separate from the form (rather than reading
// these off it) since none of it is actually submitted — the server
// re-derives all of it from tail_id, so nothing here can drift from what
// the Tails module has on file (see QuoteOfferController::store()).
const selectedTail = ref(null);

const tailLabel = (tail) => `${tail.tail} — ${tail.operator}`;

const onTailSelect = (option) => {
    selectedTail.value = option;
};

// Mirrors QuoteOfferCard's own liveFinalPrice exactly — same formula as
// QuoteOffer::calculateFinalPrice(), just previewed before the offer even
// exists yet.
const liveFinalPrice = computed(() => {
    const price = parseFloat(form.offered_price);
    const value = parseFloat(form.commission_value);

    if (Number.isNaN(price) || !form.commission_type || Number.isNaN(value)) {
        return null;
    }

    return form.commission_type === 'percentage'
        ? price * (1 + value / 100)
        : price + value;
});

const formatMoney = (value, currency) =>
    `${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`;

// Re-armed every time the modal opens (not just after a successful
// submit) — closing via the backdrop/Escape skips submit() entirely, so
// resetting only there would leave a half-filled form behind the next
// time this opens.
watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            form.reset();
            form.clearErrors();
            selectedTail.value = null;
        }
    }
);

const close = () => {
    if (form.processing) {
        return;
    }

    emit('close');
};

const submit = () => {
    form.post(route('quote-requests.offers.store', props.quoteRequestId), {
        onSuccess: () => emit('close'),
    });
};
</script>

<template>
    <Modal :show="show" max-width="lg" @close="close">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">Add Offer</h2>
            <p class="mt-1 text-sm text-gray-600">
                For an operator that responded by phone or another channel instead of email.
            </p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <InputLabel for="offer_tail" value="Aircraft" />
                    <SearchableSelect
                        id="offer_tail"
                        v-model="form.tail_id"
                        :search-url="route('tails.search')"
                        :option-label="tailLabel"
                        placeholder="Search tail number or operator…"
                        class="mt-1"
                        @select="onTailSelect"
                    >
                        <template #option="{ option }">
                            <span class="text-gray-900">{{ option.tail }}</span>
                            <span class="text-gray-500"> — {{ option.operator }}</span>
                        </template>
                    </SearchableSelect>
                    <InputError class="mt-2" :message="form.errors.tail_id" />
                </div>

                <!-- Read-only — copied from the Tail itself once picked,
                     never re-typed. See QuoteOfferController::store(). -->
                <dl
                    v-if="selectedTail"
                    class="grid grid-cols-2 gap-x-4 gap-y-2 rounded-lg bg-gray-50 p-3 text-sm"
                >
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-500">Operator</dt>
                        <dd class="text-right text-gray-900">{{ selectedTail.operator }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-500">Aircraft Type</dt>
                        <dd class="text-right text-gray-900">
                            {{ selectedTail.aircraft_speed_reference?.type_name ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-500">Year of Make</dt>
                        <dd class="text-right text-gray-900">{{ selectedTail.year_of_make ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-500">Max PAX</dt>
                        <dd class="text-right text-gray-900">{{ selectedTail.max_pax ?? '—' }}</dd>
                    </div>
                </dl>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="offer_price" value="Price" />
                        <TextInput
                            id="offer_price"
                            v-model="form.offered_price"
                            type="number"
                            min="0"
                            step="0.01"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.offered_price" />
                    </div>

                    <div>
                        <InputLabel for="offer_currency" value="Currency" />
                        <select
                            id="offer_currency"
                            v-model="form.offered_currency"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm text-gray-900 shadow-sm focus:border-accent-500 focus:ring-accent-500"
                        >
                            <option value="EUR">EUR</option>
                            <option value="RON">RON</option>
                            <option value="USD">USD</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.offered_currency" />
                    </div>
                </div>

                <div class="flex flex-wrap items-end gap-3 border-t border-gray-100 pt-4">
                    <div>
                        <InputLabel value="Commission" />
                        <select
                            v-model="form.commission_type"
                            class="mt-1 rounded-lg border-gray-300 py-2 pl-3 pr-8 text-sm text-gray-900 shadow-sm focus:border-accent-500 focus:ring-accent-500"
                        >
                            <option :value="null">None</option>
                            <option value="percentage">Percentage</option>
                            <option value="fixed">Fixed amount</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.commission_type" />
                    </div>

                    <div v-if="form.commission_type">
                        <InputLabel
                            :value="form.commission_type === 'percentage' ? '%' : `Amount (${form.offered_currency})`"
                        />
                        <input
                            v-model="form.commission_value"
                            type="number"
                            min="0"
                            step="0.01"
                            class="mt-1 block w-28 rounded-lg border-gray-300 py-2 text-sm text-gray-900 shadow-sm focus:border-accent-500 focus:ring-accent-500"
                            :placeholder="form.commission_type === 'percentage' ? 'e.g. 5' : 'e.g. 1500'"
                        />
                        <InputError class="mt-2" :message="form.errors.commission_value" />
                    </div>

                    <div v-if="liveFinalPrice !== null" class="ml-auto text-right">
                        <p class="text-xs text-gray-500">Total price</p>
                        <p class="text-base font-semibold text-gray-900">
                            {{ formatMoney(liveFinalPrice, form.offered_currency) }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="close">
                        Cancel
                    </SecondaryButton>

                    <PrimaryButton :loading="form.processing">
                        Add Offer
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>
