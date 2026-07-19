function parseInitialItems(element) {
    try {
        const items = JSON.parse(element.dataset.initialItems || '[]');
        return Array.isArray(items) ? items : [];
    } catch {
        return [];
    }
}

function parseJsonAttribute(element, attribute, fallback) {
    try {
        const value = JSON.parse(element.dataset[attribute] || 'null');
        return value ?? fallback;
    } catch {
        return fallback;
    }
}

function markCommunicationHandoff(element, type) {
    element.dataset.vueCommunicationHandoff = '1';
    return () => {
        delete element.dataset.vueCommunicationHandoff;
        delete element.dataset.vueCommunicationMounted;
        document.dispatchEvent(new CustomEvent('yuejing:vue-communication-failed', { detail: { type } }));
    };
}

function markMarkdownHandoff(element) {
    element.dataset.vueMarkdownHandoff = '1';
    return () => {
        delete element.dataset.vueMarkdownHandoff;
        delete element.dataset.vueMarkdownMounted;
        document.dispatchEvent(new CustomEvent('yuejing:vue-markdown-editor-failed', {
            detail: { marker: element },
        }));
    };
}

function markChapterManuscriptHandoff(element) {
    element.dataset.vueChapterManuscriptHandoff = '1';
    return () => {
        delete element.dataset.vueChapterManuscriptHandoff;
        delete element.dataset.vueChapterManuscriptMounted;
        document.dispatchEvent(new CustomEvent('yuejing:vue-chapter-manuscript-failed', {
            detail: { marker: element },
        }));
    };
}

function markAuthStateHandoff(element) {
    element.dataset.vueAuthStateSyncHandoff = '1';
    return () => {
        delete element.dataset.vueAuthStateSyncHandoff;
        delete element.dataset.vueAuthStateSyncMounted;
        document.dispatchEvent(new CustomEvent('yuejing:vue-auth-state-failed', {
            detail: { marker: element },
        }));
    };
}

function markToastDismissHandoff(element) {
    element.dataset.vueToastDismissHandoff = '1';
    return () => {
        delete element.dataset.vueToastDismissHandoff;
        delete element.dataset.vueToastDismissMounted;
        document.dispatchEvent(new CustomEvent('yuejing:vue-toast-dismiss-failed', {
            detail: { marker: element },
        }));
    };
}

function markTimezoneHandoff(element) {
    element.dataset.vueTimezoneHandoff = '1';
    return () => {
        delete element.dataset.vueTimezoneHandoff;
        delete element.dataset.vueTimezoneMounted;
        document.dispatchEvent(new CustomEvent('yuejing:vue-timezone-failed', {
            detail: { marker: element },
        }));
    };
}

function markRecommendationsHandoff(element) {
    element.dataset.vueRecommendationsHandoff = '1';
    return () => {
        delete element.dataset.vueRecommendationsHandoff;
        delete element.dataset.vueRecommendationsMounted;
        document.dispatchEvent(new CustomEvent('yuejing:vue-recommendations-failed', {
            detail: { marker: element },
        }));
    };
}

function markCoverPreviewHandoff(element) {
    element.dataset.vueCoverHandoff = '1';
    return () => {
        delete element.dataset.vueCoverHandoff;
        delete element.dataset.vueCoverMounted;
        document.dispatchEvent(new CustomEvent('yuejing:vue-cover-preview-failed', {
            detail: { marker: element },
        }));
    };
}

function markThemeHandoff(element) {
    element.dataset.vueThemeHandoff = '1';
    return () => {
        delete element.dataset.vueThemeHandoff;
        delete element.dataset.vueThemeMounted;
        document.dispatchEvent(new CustomEvent('yuejing:vue-theme-failed', {
            detail: { marker: element },
        }));
    };
}

async function mountAuthStateSync() {
    const body = document.body;
    const elements = body?.matches('[data-vue-auth-state-sync]') ? [body] : [];
    if (!elements.length) return;

    const rollback = elements.map((element) => markAuthStateHandoff(element));

    try {
        const [{ createApp }, { default: AuthStateSync }] = await Promise.all([
            import('vue'),
            import('./vue/AuthStateSync.vue'),
        ]);

        elements.forEach((element, index) => {
            const host = document.createElement('span');
            host.hidden = true;
            host.setAttribute('aria-hidden', 'true');
            host.dataset.vueAuthStateSyncHost = '1';
            element.append(host);

            try {
                createApp(AuthStateSync).mount(host);
            } catch (error) {
                host.remove();
                rollback[index]();
                return;
            }

            element.dataset.vueAuthStateSyncMounted = '1';
            delete element.dataset.vueAuthStateSyncHandoff;
        });
    } catch (error) {
        rollback.forEach((restore) => restore());
        throw error;
    }
}

async function mountToastDismiss() {
    const elements = [...document.querySelectorAll('[data-vue-toast-dismiss]')]
        .filter((element) => element.dataset.vueToastDismissHandoff !== '1'
            && element.dataset.vueToastDismissMounted !== '1');
    if (!elements.length) return;

    const rollback = elements.map((element) => markToastDismissHandoff(element));

    try {
        const [{ createApp }, { default: ToastDismiss }] = await Promise.all([
            import('vue'),
            import('./vue/ToastDismiss.vue'),
        ]);

        elements.forEach((element, index) => {
            let failed = false;
            createApp(ToastDismiss, { onFailure: () => { failed = true; } }).mount(element);
            if (failed) {
                rollback[index]();
                return;
            }

            element.dataset.vueToastDismissMounted = '1';
            delete element.dataset.vueToastDismissHandoff;
        });
    } catch (error) {
        rollback.forEach((restore) => restore());
        throw error;
    }
}

async function mountTimezoneLocale() {
    const body = document.body;
    const elements = body?.matches('[data-vue-timezone-sync]') ? [body] : [];
    if (!elements.length) return;

    const rollback = elements.map((element) => markTimezoneHandoff(element));

    try {
        const [{ createApp }, { default: TimezoneLocaleSync }] = await Promise.all([
            import('vue'),
            import('./vue/TimezoneLocaleSync.vue'),
        ]);

        elements.forEach((element, index) => {
            const host = document.createElement('span');
            host.hidden = true;
            host.setAttribute('aria-hidden', 'true');
            host.dataset.vueTimezoneHost = '1';
            element.append(host);

            try {
                createApp(TimezoneLocaleSync).mount(host);
            } catch {
                host.remove();
                rollback[index]();
                return;
            }

            element.dataset.vueTimezoneMounted = '1';
            delete element.dataset.vueTimezoneHandoff;
        });
    } catch (error) {
        rollback.forEach((restore) => restore());
        throw error;
    }
}

async function mountRecommendations() {
    const elements = [...document.querySelectorAll('[data-vue-recommendations]')]
        .filter((element) => element.dataset.vueRecommendationsHandoff !== '1'
            && element.dataset.vueRecommendationsMounted !== '1');
    if (!elements.length) return;

    const rollback = elements.map((element) => markRecommendationsHandoff(element));

    try {
        const [{ createApp }, { default: RecommendationFeed }] = await Promise.all([
            import('vue'),
            import('./vue/RecommendationFeed.vue'),
        ]);

        elements.forEach((element) => {
            const props = {
                apiUrl: element.dataset.apiUrl || '',
                novelBase: element.dataset.novelBase || '',
                initialItems: parseInitialItems(element),
                emptyText: element.dataset.emptyText || '',
                loadingText: element.dataset.loadingText || '',
                connectedText: element.dataset.connectedText || '',
                retryingText: element.dataset.retryingText || '',
                anonymousAuthor: element.dataset.anonymousAuthor || '',
                unnamedTitle: element.dataset.unnamedTitle || '',
            };

            createApp(RecommendationFeed, props).mount(element);
            element.dataset.vueRecommendationsMounted = '1';
            delete element.dataset.vueRecommendationsHandoff;
        });
    } catch (error) {
        rollback.forEach((restore) => restore());
        throw error;
    }
}

async function mountCoverPreviews() {
    const elements = [...document.querySelectorAll('[data-vue-cover-preview]')]
        .filter((element) => element.dataset.vueCoverHandoff !== '1'
            && element.dataset.vueCoverMounted !== '1');
    if (!elements.length) return;

    const rollback = elements.map((element) => markCoverPreviewHandoff(element));

    try {
        const [{ createApp }, { default: CoverPreview }] = await Promise.all([
            import('vue'),
            import('./vue/CoverPreview.vue'),
        ]);

        elements.forEach((element) => {
            createApp(CoverPreview, {
                inputId: element.dataset.inputId || 'cover',
                inputName: element.dataset.inputName || 'cover',
                accept: element.dataset.accept || 'image/jpeg,image/png,image/webp',
                required: element.dataset.required === '1',
                previewAlt: element.dataset.previewAlt || '',
                descriptionId: element.dataset.descriptionId || '',
            }).mount(element);
            element.dataset.vueCoverMounted = '1';
            delete element.dataset.vueCoverHandoff;
        });
    } catch (error) {
        rollback.forEach((restore) => restore());
        throw error;
    }
}

async function mountThemeToggles() {
    const elements = [...document.querySelectorAll('[data-vue-theme-toggle]')]
        .filter((element) => element.dataset.vueThemeHandoff !== '1'
            && element.dataset.vueThemeMounted !== '1');
    if (!elements.length) return;

    const rollback = elements.map((element) => markThemeHandoff(element));

    try {
        const [{ createApp }, { default: ThemeToggle }] = await Promise.all([
            import('vue'),
            import('./vue/ThemeToggle.vue'),
        ]);

        elements.forEach((element) => {
            createApp(ThemeToggle, {
                options: parseJsonAttribute(element, 'options', []),
                ariaLabel: element.dataset.ariaLabel || '',
                storageKey: element.dataset.storageKey || 'yuejing-theme',
                defaultTheme: element.dataset.defaultTheme || 'system',
            }).mount(element);
            element.dataset.vueThemeMounted = '1';
            delete element.dataset.vueThemeHandoff;
        });
    } catch (error) {
        rollback.forEach((restore) => restore());
        throw error;
    }
}

async function mountChapterLists() {
    const elements = [...document.querySelectorAll('[data-vue-chapter-list]')];
    if (!elements.length) return;

    const [{ createApp }, { default: ChapterList }] = await Promise.all([
        import('vue'),
        import('./vue/ChapterList.vue'),
    ]);

    elements.forEach((element) => {
        createApp(ChapterList, {
            chapters: parseJsonAttribute(element, 'chapters', []),
            csrfToken: element.dataset.csrfToken || '',
            translations: parseJsonAttribute(element, 'translations', {}),
        }).mount(element);
    });

    if (document.readyState !== 'loading') {
        document.dispatchEvent(new CustomEvent('yuejing:chapter-list-mounted'));
    }
}

async function mountPrivateMessages() {
    const elements = [...document.querySelectorAll('[data-vue-private-messages]')];
    if (!elements.length) return;

    const rollback = elements.map((element) => markCommunicationHandoff(element, 'messages'));

    try {
        const [{ createApp }, { default: PrivateMessages }] = await Promise.all([
            import('vue'),
            import('./vue/PrivateMessages.vue'),
        ]);

        elements.forEach((element) => {
            createApp(PrivateMessages, {
                api: parseJsonAttribute(element, 'api', {}),
                currentUserId: element.dataset.currentUserId || '',
                csrfToken: element.dataset.csrfToken || '',
                translations: parseJsonAttribute(element, 'translations', {}),
                messagesUrl: element.dataset.messagesUrl || '#',
                groupsUrl: element.dataset.groupsUrl || '#',
                embedded: element.dataset.embedded === '1',
            }).mount(element);
            element.dataset.vueCommunicationMounted = '1';
            delete element.dataset.vueCommunicationHandoff;
        });
    } catch (error) {
        rollback.forEach((restore) => restore());
        throw error;
    }
}

async function mountGroups() {
    const elements = [...document.querySelectorAll('[data-vue-groups]')];
    if (!elements.length) return;

    const rollback = elements.map((element) => markCommunicationHandoff(element, 'groups'));

    try {
        const [{ createApp }, { default: Groups }] = await Promise.all([
            import('vue'),
            import('./vue/Groups.vue'),
        ]);

        elements.forEach((element) => {
            createApp(Groups, {
                api: parseJsonAttribute(element, 'api', {}),
                currentUserId: element.dataset.currentUserId || '',
                csrfToken: element.dataset.csrfToken || '',
                translations: parseJsonAttribute(element, 'translations', {}),
                messagesUrl: element.dataset.messagesUrl || '#',
                groupsUrl: element.dataset.groupsUrl || '#',
                embedded: element.dataset.embedded === '1',
            }).mount(element);
            element.dataset.vueCommunicationMounted = '1';
            delete element.dataset.vueCommunicationHandoff;
        });
    } catch (error) {
        rollback.forEach((restore) => restore());
        throw error;
    }
}

async function mountNovelReviews() {
    const elements = [...document.querySelectorAll('[data-vue-reviews]')];
    if (!elements.length) return;

    const rollback = elements.map((element) => markCommunicationHandoff(element, 'reviews'));

    try {
        const [{ createApp }, { default: NovelReviews }] = await Promise.all([
            import('vue'),
            import('./vue/NovelReviews.vue'),
        ]);

        elements.forEach((element) => {
            createApp(NovelReviews, {
                apiUrl: element.dataset.apiUrl || '',
                rateUrl: element.dataset.rateUrl || '',
                withdrawUrl: element.dataset.withdrawUrl || '',
                loginUrl: element.dataset.loginUrl || '#',
                csrfToken: element.dataset.csrfToken || '',
                authenticated: element.dataset.authenticated === '1',
                currentRating: element.dataset.currentRating === '1',
                initialStatistics: parseJsonAttribute(element, 'initialStatistics', {}),
                initialReviews: parseJsonAttribute(element, 'initialReviews', []),
                initialForm: parseJsonAttribute(element, 'initialForm', {}),
                translations: parseJsonAttribute(element, 'translations', {}),
            }).mount(element);
            element.dataset.vueCommunicationMounted = '1';
            delete element.dataset.vueCommunicationHandoff;
        });
    } catch (error) {
        rollback.forEach((restore) => restore());
        throw error;
    }
}

async function mountReaderControls() {
    const elements = [...document.querySelectorAll('.reader-controls')];
    if (!elements.length) return;

    const rollback = elements.map((element) => markCommunicationHandoff(element, 'reader'));

    try {
        const [{ createApp }, { default: ReaderControls }] = await Promise.all([
            import('vue'),
            import('./vue/ReaderControls.vue'),
        ]);
        const translations = {
            ...(window.YuejingI18n?.reader || {}),
            ...(window.YuejingI18n?.frontend || {}),
        };

        elements.forEach((element) => {
            createApp(ReaderControls, { translations }).mount(element);
            element.dataset.vueCommunicationMounted = '1';
            delete element.dataset.vueCommunicationHandoff;
        });
    } catch (error) {
        rollback.forEach((restore) => restore());
        throw error;
    }
}

async function mountMobileMenu() {
    const elements = [...document.querySelectorAll('[data-vue-mobile-menu]')];
    if (!elements.length) return;

    const rollback = elements.map((element) => markCommunicationHandoff(element, 'mobile-menu'));

    try {
        const [{ createApp }, { default: MobileMenu }] = await Promise.all([
            import('vue'),
            import('./vue/MobileMenu.vue'),
        ]);
        const translations = window.YuejingI18n?.frontend || {};
        elements.forEach((element) => {
            createApp(MobileMenu, { translations }).mount(element);
            element.dataset.vueCommunicationMounted = '1';
            delete element.dataset.vueCommunicationHandoff;
        });
    } catch (error) {
        rollback.forEach((restore) => restore());
        throw error;
    }
}

async function mountLanguageSwitcher() {
    const elements = [...document.querySelectorAll('[data-vue-language-switcher]')];
    if (!elements.length) return;

    const rollback = elements.map((element) => markCommunicationHandoff(element, 'language'));

    try {
        const [{ createApp }, { default: LanguageSwitcher }] = await Promise.all([
            import('vue'),
            import('./vue/LanguageSwitcher.vue'),
        ]);
        elements.forEach((element) => {
            createApp(LanguageSwitcher).mount(element);
            element.dataset.vueCommunicationMounted = '1';
            delete element.dataset.vueCommunicationHandoff;
        });
    } catch (error) {
        rollback.forEach((restore) => restore());
        throw error;
    }
}

async function mountMarkdownEditors() {
    const elements = [...document.querySelectorAll('[data-vue-markdown-editor]')];
    if (!elements.length) return;

    const rollback = elements.map((element) => markMarkdownHandoff(element));

    try {
        const [{ createApp }, { default: MarkdownEditor }] = await Promise.all([
            import('vue'),
            import('./vue/MarkdownEditor.vue'),
        ]);

        elements.forEach((element, index) => {
            let failed = false;
            createApp(MarkdownEditor, {
                onFailure: () => { failed = true; },
            }).mount(element);

            if (failed) {
                rollback[index]();
                return;
            }

            element.dataset.vueMarkdownMounted = '1';
            delete element.dataset.vueMarkdownHandoff;
        });
    } catch (error) {
        rollback.forEach((restore) => restore());
        throw error;
    }
}

async function mountChapterManuscriptUploads({ includeChapterList = false } = {}) {
    const elements = [...document.querySelectorAll('[data-vue-chapter-manuscript-upload]')]
        .filter((element) => includeChapterList || !element.closest('[data-vue-chapter-list]'))
        .filter((element) => element.dataset.vueChapterManuscriptHandoff !== '1'
            && element.dataset.vueChapterManuscriptMounted !== '1');
    if (!elements.length) return;

    const rollback = elements.map((element) => markChapterManuscriptHandoff(element));

    try {
        const [{ createApp }, { default: ChapterManuscriptUpload }] = await Promise.all([
            import('vue'),
            import('./vue/ChapterManuscriptUpload.vue'),
        ]);

        elements.forEach((element, index) => {
            let failed = false;
            createApp(ChapterManuscriptUpload, {
                onFailure: () => { failed = true; },
            }).mount(element);

            if (failed) {
                rollback[index]();
                return;
            }

            element.dataset.vueChapterManuscriptMounted = '1';
            delete element.dataset.vueChapterManuscriptHandoff;
        });
    } catch (error) {
        rollback.forEach((restore) => restore());
        throw error;
    }
}

document.addEventListener('yuejing:chapter-list-mounted', () => {
    void mountChapterManuscriptUploads({ includeChapterList: true }).catch(() => {});
});

void mountRecommendations().catch(() => {});
void mountAuthStateSync().catch(() => {});
void mountCoverPreviews().catch(() => {});
void mountThemeToggles().catch(() => window.YuejingThemeFallback?.());
void mountChapterLists().catch(() => {});
void mountPrivateMessages().catch(() => {});
void mountGroups().catch(() => {});
void mountNovelReviews().catch(() => {});
void mountReaderControls().catch(() => {});
void mountMobileMenu().catch(() => {});
void mountLanguageSwitcher().catch(() => {});
void mountMarkdownEditors().catch(() => {});
void mountChapterManuscriptUploads().catch(() => {});
void mountToastDismiss().catch(() => {});
void mountTimezoneLocale().catch(() => {});
