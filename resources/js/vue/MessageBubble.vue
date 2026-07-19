<script setup>
import { computed } from 'vue';
import {
    entityId,
    entityName,
    formatCommunicationTime,
    messageRead,
    messageReadStats,
    messageText,
    translate,
} from './useCommunicationApi.js';

const props = defineProps({
    message: {
        type: Object,
        required: true,
    },
    currentUserId: {
        type: [String, Number],
        required: true,
    },
    translations: {
        type: Object,
        default: () => ({}),
    },
    group: {
        type: Boolean,
        default: false,
    },
    recipientName: {
        type: String,
        default: '',
    },
});

const sender = computed(() => props.message.sender || props.message.from_user || props.message.user || {});
const receiver = computed(() => props.message.receiver || props.message.to_user || props.message.recipient || {});
const own = computed(() => String(props.message.sender_id ?? sender.value.id) === String(props.currentUserId));
const senderName = computed(() => own.value
    ? translate(props.translations, 'me')
    : entityName(sender.value, props.message.sender_name || translate(props.translations, 'other')));
const receiverName = computed(() => entityName(
    receiver.value,
    props.recipientName || props.message.recipient_name || (props.group
        ? translate(props.translations, 'group_member')
        : own.value ? translate(props.translations, 'other') : translate(props.translations, 'me')),
));
const status = computed(() => {
    if (props.group) {
        const stats = messageReadStats(props.message);
        if (!stats) return '';
        const count = Array.isArray(stats)
            ? stats.length
            : stats.read ?? stats.count ?? stats.total ?? 0;
        return translate(props.translations, 'read_count', { count });
    }

    return messageRead(props.message)
        ? translate(props.translations, 'read')
        : translate(props.translations, 'unread');
});
const text = computed(() => String(messageText(props.message)));
const createdAt = computed(() => props.message.created_at || props.message.createdAt || '');
const messageId = computed(() => entityId(props.message));
</script>

<template>
    <article class="message-bubble-row" :class="{ 'is-own': own }" :data-message-id="messageId">
        <div class="message-bubble">
            <div class="message-byline">
                <strong>{{ senderName }}</strong><span aria-hidden="true">→</span><span>{{ receiverName }}</span>
            </div>
            <p class="message-body">{{ text }}</p>
            <div class="message-meta">
                <time :datetime="createdAt">{{ formatCommunicationTime(createdAt) }}</time>
                <span v-if="status">{{ status }}</span>
            </div>
        </div>
    </article>
</template>

<style scoped>
.message-body {
    white-space: pre-wrap;
}
</style>
