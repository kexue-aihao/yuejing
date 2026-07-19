<script setup>
import { computed, useAttrs } from 'vue';
import { LoaderCircle } from 'lucide-vue-next';

const props = defineProps({
    href: { type: String, default: '' },
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
    icon: { type: Object, default: null },
});

const attrs = useAttrs();

const classes = computed(() => [
    'button',
    'ui-button',
    `button-${props.variant === 'ghost' ? 'ghost' : props.variant}`,
    props.size === 'sm' ? 'button-small' : '',
    `ui-button-${props.variant}`,
    `ui-button-${props.size}`,
    { 'ui-button-block': props.block, 'is-loading': props.loading },
]);

const isDisabled = computed(() => props.disabled || props.loading);
</script>

<template>
    <a v-if="href && !isDisabled" v-bind="attrs" :href="href" :class="classes" :aria-busy="loading || undefined">
        <LoaderCircle v-if="loading" class="ui-button-icon ui-button-spinner" aria-hidden="true" />
        <component :is="icon" v-else-if="icon" class="ui-button-icon" aria-hidden="true" />
        <span class="ui-button-label"><slot /></span>
    </a>
    <span v-else-if="href" v-bind="attrs" :class="classes" aria-disabled="true" :aria-busy="loading || undefined">
        <LoaderCircle v-if="loading" class="ui-button-icon ui-button-spinner" aria-hidden="true" />
        <component :is="icon" v-else-if="icon" class="ui-button-icon" aria-hidden="true" />
        <span class="ui-button-label"><slot /></span>
    </span>
    <button v-else v-bind="attrs" :type="type" :class="classes" :disabled="isDisabled" :aria-busy="loading || undefined">
        <LoaderCircle v-if="loading" class="ui-button-icon ui-button-spinner" aria-hidden="true" />
        <component :is="icon" v-else-if="icon" class="ui-button-icon" aria-hidden="true" />
        <span class="ui-button-label"><slot /></span>
    </button>
</template>
