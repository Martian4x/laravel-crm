@props([
    'allowEdit' => true,
])

<v-inline-phone-edit
    {{ $attributes->except('value') }}
    :value={{ json_encode($attributes->get('value')) }}
    :allow-edit="{{ $allowEdit ? 'true' : 'false' }}"
>
    <div class="group w-full max-w-full hover:rounded-sm">
        <div class="rounded-xs flex h-[34px] items-center pl-2.5 text-left">
            <div class="shimmer h-5 w-48 rounded border border-transparent"></div>
        </div>
    </div>
</v-inline-phone-edit>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-inline-phone-edit-template"
    >
        <div class="group w-full max-w-full hover:rounded-sm">
            <!-- Non-editing view -->
            <div
                class="flex h-[34px] items-center rounded border border-transparent transition-all"
                :class="allowEdit ? 'hover:bg-gray-100 dark:hover:bg-gray-800' : ''"
            >
                <div 
                    class="group relative !w-full pl-2.5"
                    :style="{ 'text-align': position }"
                >
                    <span class="cursor-pointer truncate rounded">
                        <template v-if="valueLabel">
                            @{{ valueLabel }}
                        </template>

                        <template v-else>
                            <template v-for="(item, index) in inputValue">
                                <a
                                    class="hover:text-brandColor hover:underline"
                                    :href="`tel:${item.value}`"
                                    @click.stop
                                >@{{ item.value }}</a>

                                <a
                                    class="text-xs font-semibold text-brandColor hover:underline"
                                    :href="whatsAppHref(item.value)"
                                    target="_blank"
                                    rel="noopener"
                                    title="WhatsApp"
                                    @click.stop
                                >WA</a><span v-if="index < inputValue.length - 1">,&nbsp;</span>
                            </template>
                        </template>
                    </span>

                    <div
                        class="absolute bottom-0 mb-5 hidden flex-col group-hover:flex"
                        v-if="inputValue.map(item => `${item.value}(${item.label})`).join(', ').length > 20"
                    >
                        <span class="whitespace-no-wrap relative z-10 rounded-md bg-black px-4 py-2 text-xs leading-none text-white shadow-lg dark:bg-white dark:text-gray-900">
                            @{{ inputValue.map(item => `${item.value}(${item.label})`).join(', \n') }}
                        </span>

                        <div class="-mt-2 ml-4 h-3 w-3 rotate-45 bg-black dark:bg-white"></div>
                    </div>
                </div>

                <template v-if="allowEdit">
                    <i
                        @click="toggle"
                        class="icon-edit cursor-pointer rounded p-0.5 text-2xl opacity-0 hover:bg-gray-200 group-hover:opacity-100 dark:hover:bg-gray-950 ltr:mr-1 rtl:ml-1"
                    ></i>
                </template>
            </div>

            <Teleport to="body">
                <x-admin::form
                    v-slot="{ meta, errors, handleSubmit }"
                    as="div"
                    ref="modalForm"
                >
                    <form @submit="handleSubmit($event, updateOrCreate)">
                        <!-- Editing view -->
                        <x-admin::modal ref="phoneNumberModal">
                            <!-- Modal Header -->
                            <x-slot:header>
                                <p class="text-lg font-bold text-gray-800 dark:text-white">
                                    @lang("admin::app.common.custom-attributes.update-contact-title")
                                </p>
                            </x-slot>

                            <!-- Modal Content -->
                            <x-slot:content>
                                <template v-for="(contactNumber, index) in contactNumbers">
                                    <div class="mb-2 flex items-center">
                                        <x-admin::form.control-group.control
                                            type="text"
                                            ::id="`${name}[${index}].value`"
                                            ::name="`${name}[${index}].value`"
                                            class="!rounded-r-none"
                                            ::rules="getValidation"
                                            :label="trans('admin::app.common.custom-attributes.contact')"
                                            v-model="contactNumber.value"
                                        />

                                        <div class="relative">
                                            <x-admin::form.control-group.control
                                                type="select"
                                                ::id="`${name}[${index}].label`"
                                                ::name="`${name}[${index}].label`"
                                                class="!w-24 !rounded-l-none"
                                                ::value="contactNumber.label"
                                            >
                                                <option value="work">@lang('admin::app.common.custom-attributes.work')</option>
                                                <option value="home">@lang('admin::app.common.custom-attributes.home')</option>
                                            </x-admin::form.control-group.control>
                                        </div>

                                        <i
                                            v-if="contactNumbers.length > 1"
                                            class="icon-delete ml-1 cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                                            @click="remove(contactNumber)"
                                        ></i>
                                    </div>
                        
                                    <x-admin::form.control-group.error ::name="`${name}[${index}].value`"/>
                                </template>
                        
                                <button
                                    type="button"
                                    class="flex max-w-max items-center gap-2 text-brandColor"
                                    @click="add"
                                >
                                    <i class="icon-add text-md !text-brandColor"></i>

                                    @lang("admin::app.common.custom-attributes.add-more")
                                </button>
                            </x-slot>

                            <!-- Modal Footer -->
                            <x-slot:footer>
                                <!-- Save Button -->
                                <x-admin::button
                                    button-type="submit"
                                    class="primary-button justify-center"
                                    :title="trans('admin::app.common.custom-attributes.save')"
                                    ::loading="isProcessing"
                                    ::disabled="isProcessing"
                                />
                            </x-slot>
                        </x-admin::modal>
                    </form>
                </x-admin::form>
            </Teleport>
        </div>
    </script>

    <script type="module">
        app.component('v-inline-phone-edit', {
            template: '#v-inline-phone-edit-template',

            emits: ['on-save'],

            props: {
                name: {
                    type: String,
                    required: true,
                },

                value: {
                    required: true,
                },

                rules: {
                    type: String,
                    default: '',
                },

                label: {
                    type: String,
                    default: '',
                },

                placeholder: {
                    type: String,
                    default: '',
                },

                position: {
                    type: String,
                    default: 'right',
                },

                allowEdit: {
                    type: Boolean,
                    default: true,
                },

                errors: {
                    type: Object,
                    default: {},
                },

                url: {
                    type: String,
                    default: '',
                },

                valueLabel: {
                    type: String,
                    default: '',
                },
            },

            data() {
                return {
                    inputValue: this.value,

                    isEditing: false,

                    contactNumbers: JSON.parse(JSON.stringify(this.value || [{'value': '', 'label': 'work'}])),

                    isProcessing: false,

                    isRTL: document.documentElement.dir === 'rtl',
                };
            },

            watch: {
                /**
                 * Watch the value prop.
                 * 
                 * @param {String} newValue 
                 */
                value(newValue, oldValue) {
                    if (JSON.stringify(newValue) !== JSON.stringify(oldValue)) {
                        this.contactNumbers = newValue || [{'value': '', 'label': 'work'}];
                    }
                },
            },

            created() {
                this.extendValidations();

                if (! this.contactNumbers || ! this.contactNumbers.length) {
                    this.contactNumbers = [{
                        'value': '',
                        'label': 'work'
                    }];
                }
            },

            computed: {
                /**
                 * Get the validation rules.
                 * 
                 * @return {Object}
                 */
                getValidation() {
                    return {
                        phone: true,
                        unique_contact_number: this.contactNumbers ?? [],
                        required: true,
                    };
                },
            },

            methods: {
                /**
                 * WhatsApp deep link: local numbers (07XX/06XX) are converted
                 * to international format with Tanzania's country code.
                 *
                 * This mirrors Azimio\Messaging\Support\PhoneNormalizer, which
                 * is CANONICAL — it is what the SMS module actually sends and
                 * matches opt-outs against. This copy exists only because the
                 * link is built in the browser and cannot call PHP. Keep the
                 * two in step; if they ever disagree, the PHP is right.
                 *
                 * Intentionally simpler than the PHP: a deep link that goes
                 * nowhere is a dead click, whereas a badly normalised SMS
                 * costs money, so validation lives on the server side.
                 *
                 * @param {String} number
                 * @return {String}
                 */
                whatsAppHref(number) {
                    let digits = String(number ?? '').replace(/\D/g, '');

                    // "00" is the ITU long form of "+".
                    if (digits.startsWith('00')) {
                        digits = digits.slice(2);
                    }

                    // A leading 0 is the national trunk prefix.
                    if (digits.startsWith('0')) {
                        digits = digits.replace(/^0+/, '');

                        if (! digits.startsWith('255')) {
                            digits = '255' + digits;
                        }
                    } else if (digits.length === 9) {
                        // A bare national number, e.g. 712345678.
                        digits = '255' + digits;
                    }

                    return `https://wa.me/${digits}`;
                },

                /**
                 * Toggle the input.
                 *
                 * @return {void}
                 */
                toggle() {
                    this.isEditing = true;

                    this.$refs.phoneNumberModal.toggle();
                },

                add() {
                    this.contactNumbers.push({
                        'value': '',
                        'label': 'work'
                    });
                },

                remove(contactNumber) {
                    this.contactNumbers = this.contactNumbers.filter(number => number !== contactNumber);
                },

                extendValidations() {
                    defineRule('unique_contact_number', (value, contactNumbers) => {
                        if (
                            ! value
                            || ! value.length
                        ) {
                            return true;
                        }

                        const phoneOccurrences = contactNumbers.filter(contactNumber => contactNumber.value === value).length;

                        if (phoneOccurrences > 1) {
                            return 'This phone number is already used';
                        }

                        return true;
                    });
                },

                updateOrCreate(params) {
                    this.inputValue = params.contact_numbers || this.inputValue;

                    if (this.url) {
                        this.isProcessing = true;

                        this.$axios.put(this.url, {
                                [this.name]: this.inputValue,
                            })
                            .then((response) => {
                                this.contactNumbers = response.data.data.contact_numbers || this.contactNumbers;

                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                            })
                            .catch((error) => {
                                this.inputValue = this.value;

                                this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                            })
                            .finally(() => {
                                this.isProcessing = false;
                            });
                    }

                    this.$emit('on-save', params);

                    this.$refs.phoneNumberModal.toggle();
                }
            },
        });
    </script>
@endPushOnce