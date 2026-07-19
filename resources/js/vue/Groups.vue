<script setup>
import { computed, ref } from 'vue';
import MessageBubble from './MessageBubble.vue';
import { entityId, entityName, translate } from './useCommunicationApi.js';
import { useGroups } from './useGroups.js';

const props = defineProps({
    api: {
        type: Object,
        required: true,
    },
    currentUserId: {
        type: [String, Number],
        required: true,
    },
    csrfToken: {
        type: String,
        default: '',
    },
    translations: {
        type: Object,
        default: () => ({}),
    },
    messagesUrl: {
        type: String,
        default: '#',
    },
    groupsUrl: {
        type: String,
        default: '#',
    },
    embedded: {
        type: Boolean,
        default: false,
    },
});

const groupName = ref('');
const selectedMemberIds = ref([]);
const memberToAdd = ref('');
const messageBody = ref('');
const actionError = ref('');
const isCreating = ref(false);
const isAdding = ref(false);
const isSending = ref(false);

const {
    groups,
    users,
    members,
    messages,
    activeId,
    title,
    meta,
    help,
    status,
    statusState,
    listError,
    userId,
    selectGroup,
    createGroup,
    addMember,
    removeMember,
    sendMessage,
} = useGroups({
    api: () => props.api,
    currentUserId: () => props.currentUserId,
    csrfToken: () => props.csrfToken,
    translations: () => props.translations,
});

const availableUsers = computed(() => users.value.filter((user) => String(entityId(user)) !== String(userId)));
const removableMembers = computed(() => members.value.filter((member) => String(entityId(member.user || member, member.user_id)) !== String(userId)));

function t(key, replacements = {}) {
    return translate(props.translations, key, replacements);
}

function initial(value) {
    return String(value || '?').slice(0, 1).toUpperCase();
}

function groupId(group) {
    return String(entityId(group));
}

function groupNameFor(group) {
    return entityName(group, t('unnamed_group'));
}

function groupMemberCount(group) {
    return group?.member_count ?? group?.members_count ?? group?.members?.length ?? '';
}

function memberUser(member) {
    return member?.user || member;
}

function memberId(member) {
    return String(entityId(memberUser(member), member?.user_id));
}

function memberName(member) {
    return entityName(memberUser(member), t('unnamed_user'));
}

async function chooseGroup(group) {
    actionError.value = '';
    try {
        await selectGroup(groupId(group));
    } catch (error) {
        actionError.value = error.message;
    }
}

async function handleCreate() {
    if (!groupName.value.trim()) return;
    isCreating.value = true;
    actionError.value = '';
    try {
        await createGroup(groupName.value, selectedMemberIds.value);
        groupName.value = '';
        selectedMemberIds.value = [];
    } catch (error) {
        actionError.value = error.message;
    } finally {
        isCreating.value = false;
    }
}

async function handleAddMember() {
    if (!activeId.value || !memberToAdd.value) return;
    isAdding.value = true;
    actionError.value = '';
    try {
        await addMember(memberToAdd.value);
        memberToAdd.value = '';
    } catch (error) {
        actionError.value = error.message;
    } finally {
        isAdding.value = false;
    }
}

async function handleRemoveMember(id) {
    actionError.value = '';
    try {
        await removeMember(id);
    } catch (error) {
        actionError.value = error.message;
    }
}

async function handleSend() {
    const body = messageBody.value.trim();
    if (!activeId.value || !body) return;
    isSending.value = true;
    actionError.value = '';
    try {
        await sendMessage(body);
        messageBody.value = '';
    } catch (error) {
        actionError.value = error.message;
    } finally {
        isSending.value = false;
    }
}
</script>

<template>
    <div class="communication-page groups-page" :class="{ 'embedded-communication-page': embedded }">
        <div class="communication-head">
            <div>
                <p class="eyebrow">{{ t('groups_eyebrow') }}</p>
                <h1>{{ t('groups_title') }}</h1>
                <p>{{ t('groups_intro') }}</p>
            </div>
            <nav class="communication-switcher" :aria-label="t('entry_label')">
                <a :href="messagesUrl">{{ t('messages') }}</a>
                <a class="is-active" :href="groupsUrl" aria-current="page">{{ t('groups') }}</a>
            </nav>
        </div>

        <div class="communication-layout groups-layout">
            <aside class="communication-sidebar panel">
                <div class="panel-heading">
                    <div><p class="panel-kicker">{{ t('groups_label') }}</p><h2>{{ t('groups') }}</h2></div>
                    <span class="live-dot" :aria-label="t('live')"></span>
                </div>
                <div class="group-list" aria-live="polite">
                    <p v-if="listError" class="communication-error">{{ listError }}</p>
                    <p v-else-if="!groups.length" class="communication-empty">{{ t('empty_groups') }}</p>
                    <button v-for="group in groups" :key="groupId(group)" type="button" class="conversation-item" :class="{ 'is-active': groupId(group) === activeId }" @click="chooseGroup(group)">
                        <span class="avatar avatar-small">{{ initial(groupNameFor(group)) }}</span>
                        <span class="conversation-copy"><strong>{{ groupNameFor(group) }}</strong><small>{{ groupMemberCount(group) === '' ? t('group_label') : t('member_count', { count: groupMemberCount(group) }) }}</small></span>
                    </button>
                </div>
                <form class="group-create-form" method="post" :action="api.store" @submit.prevent="handleCreate">
                    <h3>{{ t('create_group') }}</h3>
                    <label class="form-field"><span>{{ t('group_name') }}</span><input v-model="groupName" name="name" :placeholder="t('group_name_placeholder')" required :disabled="isCreating"></label>
                    <fieldset class="member-picker">
                        <legend>{{ t('choose_members') }}</legend>
                        <label v-for="user in availableUsers" :key="entityId(user)" class="check-option"><input v-model="selectedMemberIds" type="checkbox" name="member_ids[]" :value="entityId(user)" :disabled="isCreating"><span>{{ entityName(user, t('unnamed_user')) }}</span></label>
                        <p v-if="!availableUsers.length" class="form-help">{{ t('no_invitable') }}</p>
                    </fieldset>
                    <button class="button button-dark" type="submit" :disabled="isCreating || !groupName.trim()">{{ t('create_group_button') }} <span aria-hidden="true">→</span></button>
                </form>
                <noscript><p class="no-script-note">{{ t('noscript_groups_create') }}</p></noscript>
            </aside>

            <section class="communication-main panel" aria-labelledby="vue-group-title">
                <div class="panel-heading communication-main-heading">
                    <div>
                        <p class="panel-kicker">{{ t('group_chat') }}</p>
                        <h2 id="vue-group-title">{{ title }}</h2>
                        <p class="panel-subtitle">{{ meta }}</p>
                    </div>
                    <span class="connection-status" :data-state="statusState" role="status" aria-live="polite">{{ status }}</span>
                </div>

                <div class="group-members">
                    <div class="section-label"><span>{{ t('members') }}</span><span>{{ t('member_count', { count: members.length }) }}</span></div>
                    <div class="member-chips">
                        <span v-if="!members.length" class="muted">{{ t('members_after_choose') }}</span>
                        <span v-for="member in members" :key="memberId(member)" class="member-chip">
                            <span class="avatar avatar-tiny">{{ initial(memberName(member)) }}</span>{{ memberName(member) }}
                            <button v-if="memberId(member) !== userId" type="button" :title="t('remove_member')" :aria-label="t('remove_member')" @click="handleRemoveMember(memberId(member))">×</button>
                        </span>
                    </div>
                    <form class="member-add-form" method="post" :action="api.addMember" @submit.prevent="handleAddMember">
                        <label class="sr-only" for="vue-group-member-select">{{ t('invite_member') }}</label>
                        <select id="vue-group-member-select" v-model="memberToAdd" name="user_id" :disabled="!activeId || isAdding">
                            <option value="">{{ t('choose_member') }}</option>
                            <option v-for="user in availableUsers" :key="entityId(user)" :value="entityId(user)">{{ entityName(user, t('unnamed_user')) }}</option>
                        </select>
                        <button class="button button-outline button-small" type="submit" :disabled="!activeId || !memberToAdd || isAdding">{{ t('add_member') }}</button>
                    </form>
                </div>

                <div class="message-list" aria-live="polite" :aria-label="t('group_content')">
                    <MessageBubble v-for="message in messages" :key="message.id" :message="message" :current-user-id="userId" :translations="translations" group />
                    <p v-if="!messages.length" class="communication-empty">{{ activeId ? t('empty_group_messages') : t('choose_group_hint') }}</p>
                </div>

                <form class="message-compose" method="post" :action="api.sendMessage" @submit.prevent="handleSend">
                    <label class="sr-only" for="vue-group-message-body">{{ t('input_group_message') }}</label>
                    <textarea id="vue-group-message-body" v-model="messageBody" name="body" rows="3" :placeholder="t('group_placeholder')" required :disabled="isSending"></textarea>
                    <div class="compose-actions">
                        <span class="form-help">{{ actionError || help }}</span>
                        <button class="button button-primary" type="submit" :disabled="!activeId || isSending || !messageBody.trim()">{{ t('send_group_message') }} <span aria-hidden="true">→</span></button>
                    </div>
                </form>
                <noscript><p class="no-script-note">{{ t('noscript_groups_send') }}</p></noscript>
            </section>
        </div>
    </div>
</template>
