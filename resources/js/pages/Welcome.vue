<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type EventRow = {
    id: number;
    source: string;
    message: string;
    occurred_at: string;
};

type TriageGroup = {
    id: number;
    signature: string;
    severity: 'info' | 'warn' | 'error';
    owner: string;
    count: number;
    sample_ids: number[];
    reasons: string[];
};

type TriageRun = {
    id: number;
    status: string;
    event_count: number;
    group_count: number;
    completed_at: string | null;
    groups: TriageGroup[];
};

const props = defineProps<{
    events: EventRow[];
    run: TriageRun | null;
    llmMode: string;
}>();

const events = ref(props.events);
const run = ref(props.run);
const loading = ref(false);
const errorMessage = ref('');
const expandedGroups = ref<number[]>([]);
const query = ref('');
const severityFilter = ref('all');

const groups = computed(() => {
    const all = run.value?.groups ?? [];
    return all.filter((group) => {
        const matchesSeverity =
            severityFilter.value === 'all' ||
            group.severity === severityFilter.value;
        const searchText =
            `${group.signature} ${group.owner} ${group.reasons.join(' ')}`.toLowerCase();
        return (
            matchesSeverity && searchText.includes(query.value.toLowerCase())
        );
    });
});

const logsById = computed(
    () => new Map(events.value.map((event) => [event.id, event])),
);
const totalEvents = computed(
    () => run.value?.event_count ?? events.value.length,
);
const openGroups = computed(
    () =>
        (run.value?.groups ?? []).filter((group) => group.severity === 'error')
            .length,
);
const lastRunLabel = computed(() => {
    if (!run.value?.completed_at) return 'Not run yet';
    return new Intl.DateTimeFormat('en', {
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(run.value.completed_at));
});

function formatTime(value: string): string {
    return new Intl.DateTimeFormat('en', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(new Date(value));
}

function toggleGroup(id: number): void {
    expandedGroups.value = expandedGroups.value.includes(id)
        ? expandedGroups.value.filter((groupId) => groupId !== id)
        : [...expandedGroups.value, id];
}

async function runTriage(): Promise<void> {
    loading.value = true;
    errorMessage.value = '';
    try {
        const csrfToken =
            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.content ?? '';
        const response = await fetch('/triage', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({}),
        });
        const payload = await response.json();
        if (!response.ok || payload.message) {
            throw new Error(
                payload.message ??
                    'The triage run could not finish. Please try again.',
            );
        }
        run.value = payload.run as TriageRun;
        events.value = payload.events as EventRow[];
        expandedGroups.value = [];
    } catch (error) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Unexpected error. Please try again.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <Head title="Log Triage Desk" />
    <div class="desk-shell">
        <aside class="sidebar">
            <a class="brand" href="/" aria-label="Harbor Desk home">
                <span class="brand-mark"
                    ><span></span><span></span><span></span
                ></span>
                <span class="brand-name">harbor<span>desk</span></span>
            </a>

            <div class="workspace-label">WORKSPACE</div>
            <button class="workspace-switcher" type="button">
                <span class="workspace-icon">H</span>
                <span class="workspace-copy"
                    ><strong>Harbor Desk</strong
                    ><small>Demo workspace</small></span
                >
                <span class="chevron">⌄</span>
            </button>

            <nav class="main-nav" aria-label="Main navigation">
                <a href="#overview" class="nav-link"
                    ><span class="nav-icon">◫</span> Overview</a
                >
                <a href="#triage" class="nav-link active"
                    ><span class="nav-icon">⌕</span> Log triage
                    <span class="nav-count">{{ totalEvents }}</span></a
                >
                <a href="#events" class="nav-link"
                    ><span class="nav-icon">≋</span> Events</a
                >
            </nav>

            <div class="sidebar-bottom">
                <div class="sidebar-status">
                    <span class="status-dot"></span
                    ><span>All systems operational</span>
                </div>
                <div class="profile-row">
                    <div class="avatar">HD</div>
                    <div>
                        <strong>Harbor Desk</strong
                        ><small>Engineering team</small>
                    </div>
                    <span class="more">···</span>
                </div>
                <div class="sidebar-footer">
                    FICTIONAL DEMO DATA <span>v1.0.0</span>
                </div>
            </div>
        </aside>

        <main class="main-content" id="overview">
            <header class="topbar">
                <div class="breadcrumbs">
                    <span>Workspace</span><span class="crumb-divider">/</span
                    ><strong>Log triage</strong>
                </div>
                <div class="topbar-right">
                    <span class="env-pill"><i></i> Demo environment</span
                    ><button
                        class="help-button"
                        type="button"
                        aria-label="Help"
                    >
                        ?
                    </button>
                    <div class="top-avatar">HD</div>
                </div>
            </header>

            <div class="page-wrap">
                <section class="page-heading">
                    <div>
                        <div class="eyebrow">
                            <span class="eyebrow-line"></span> INCIDENT
                            WORKSPACE
                        </div>
                        <h1>Log triage</h1>
                        <p class="page-subtitle">
                            Turn noisy application logs into clear, actionable
                            incidents.
                        </p>
                    </div>
                    <button
                        class="run-button"
                        type="button"
                        :disabled="loading || events.length === 0"
                        @click="runTriage"
                    >
                        <span v-if="loading" class="spinner"></span>
                        <svg v-else viewBox="0 0 20 20" aria-hidden="true">
                            <path d="M10 3v14M3 10h14" />
                        </svg>
                        {{
                            loading
                                ? 'Analyzing logs…'
                                : run
                                  ? 'Run triage again'
                                  : 'Run triage'
                        }}
                    </button>
                </section>

                <div v-if="errorMessage" class="error-banner" role="alert">
                    <span>!</span>{{ errorMessage }}
                </div>

                <section class="metrics-grid" aria-label="Triage overview">
                    <article class="metric-card">
                        <div class="metric-top">
                            <span>LOG EVENTS</span
                            ><span class="metric-icon violet">≋</span>
                        </div>
                        <div class="metric-value">
                            {{ totalEvents }}<small>events</small>
                        </div>
                        <div class="metric-foot">
                            <span class="metric-spark violet-spark"
                                >▁▂▁▃▂▅▃▆▄</span
                            ><span>Across all sources</span>
                        </div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-top">
                            <span>INCIDENT GROUPS</span
                            ><span class="metric-icon blue">⌘</span>
                        </div>
                        <div class="metric-value">
                            {{ run?.group_count ?? '—'
                            }}<small>{{
                                run ? 'groups found' : 'run triage to start'
                            }}</small>
                        </div>
                        <div class="metric-foot">
                            <span class="metric-foot-dot blue-dot"></span
                            ><span>Normalized + clustered</span>
                        </div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-top">
                            <span>NEEDS ATTENTION</span
                            ><span class="metric-icon amber">↗</span>
                        </div>
                        <div class="metric-value">
                            {{ run ? openGroups : '—'
                            }}<small>{{
                                run ? 'error groups' : 'awaiting analysis'
                            }}</small>
                        </div>
                        <div class="metric-foot">
                            <span class="metric-foot-dot amber-dot"></span
                            ><span>Prioritized by severity</span>
                        </div>
                    </article>
                    <article class="metric-card model-card">
                        <div class="metric-top">
                            <span>TRIAGE ENGINE</span
                            ><span class="model-live"><i></i> READY</span>
                        </div>
                        <div class="model-name">
                            <span class="sparkle">✳</span> {{ llmMode }}
                        </div>
                        <div class="metric-foot">
                            <span class="metric-foot-dot green-dot"></span
                            ><span>TF-IDF clustering · 12s timeout</span>
                        </div>
                    </article>
                </section>

                <section class="content-grid" id="triage">
                    <div class="groups-panel panel">
                        <div class="panel-header">
                            <div>
                                <div class="section-kicker">YOUR QUEUE</div>
                                <h2>
                                    {{
                                        run
                                            ? 'Incident groups'
                                            : 'Ready for triage'
                                    }}
                                    <span class="heading-count">{{
                                        run ? run.group_count : events.length
                                    }}</span>
                                </h2>
                            </div>
                            <div v-if="run" class="last-run">
                                <span class="last-run-dot"></span> Last run
                                {{ lastRunLabel }}
                            </div>
                            <div v-else class="sample-badge">
                                <span></span> 12 sample events
                            </div>
                        </div>

                        <template v-if="run">
                            <div class="group-toolbar">
                                <div
                                    class="filter-tabs"
                                    role="tablist"
                                    aria-label="Filter incidents by severity"
                                >
                                    <button
                                        v-for="filter in [
                                            'all',
                                            'error',
                                            'warn',
                                            'info',
                                        ]"
                                        :key="filter"
                                        type="button"
                                        :class="[
                                            'filter-tab',
                                            {
                                                selected:
                                                    severityFilter === filter,
                                            },
                                        ]"
                                        @click="severityFilter = filter"
                                    >
                                        {{
                                            filter === 'all'
                                                ? 'All groups'
                                                : filter
                                        }}
                                        <span v-if="filter === 'all'">{{
                                            run.group_count
                                        }}</span>
                                    </button>
                                </div>
                                <label class="search-box"
                                    ><span>⌕</span
                                    ><input
                                        v-model="query"
                                        aria-label="Search groups"
                                        placeholder="Search groups"
                                /></label>
                            </div>

                            <div v-if="groups.length" class="group-list">
                                <article
                                    v-for="(group, index) in groups"
                                    :key="group.id"
                                    class="group-card"
                                >
                                    <button
                                        class="group-summary"
                                        type="button"
                                        :aria-expanded="
                                            expandedGroups.includes(group.id)
                                        "
                                        @click="toggleGroup(group.id)"
                                    >
                                        <span
                                            :class="[
                                                'severity-rail',
                                                `rail-${group.severity}`,
                                            ]"
                                        ></span>
                                        <span class="group-number">{{
                                            String(index + 1).padStart(2, '0')
                                        }}</span>
                                        <span
                                            class="severity-icon"
                                            :class="`severity-${group.severity}`"
                                            >{{
                                                group.severity === 'error'
                                                    ? '!'
                                                    : group.severity === 'warn'
                                                      ? '△'
                                                      : '·'
                                            }}</span
                                        >
                                        <span class="group-main"
                                            ><span class="group-title">{{
                                                group.signature
                                            }}</span
                                            ><span class="group-meta"
                                                ><span
                                                    >{{ group.count }} related
                                                    {{
                                                        group.count === 1
                                                            ? 'event'
                                                            : 'events'
                                                    }}</span
                                                ><span class="meta-dot">·</span
                                                ><span>{{
                                                    group.owner
                                                }}</span></span
                                            ></span
                                        >
                                        <span
                                            :class="[
                                                'severity-pill',
                                                `pill-${group.severity}`,
                                            ]"
                                            >{{ group.severity }}</span
                                        >
                                        <span class="group-expand">{{
                                            expandedGroups.includes(group.id)
                                                ? '−'
                                                : '+'
                                        }}</span>
                                    </button>
                                    <div
                                        v-if="expandedGroups.includes(group.id)"
                                        class="group-details"
                                    >
                                        <div class="ai-note">
                                            <span class="note-star">✳</span>
                                            <div>
                                                <strong
                                                    >Why this was
                                                    flagged</strong
                                                >
                                                <p
                                                    v-for="reason in group.reasons"
                                                    :key="reason"
                                                >
                                                    {{ reason }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="sample-heading">
                                            SAMPLE LOGS
                                            <span>{{ group.count }} total</span>
                                        </div>
                                        <div
                                            v-for="id in group.sample_ids"
                                            :key="id"
                                            class="sample-row"
                                        >
                                            <time>{{
                                                logsById.get(id)
                                                    ? formatTime(
                                                          logsById.get(id)!
                                                              .occurred_at,
                                                      )
                                                    : '—'
                                            }}</time>
                                            <span class="sample-source">{{
                                                logsById.get(id)?.source ??
                                                'Unknown source'
                                            }}</span>
                                            <code>{{
                                                logsById.get(id)?.message ??
                                                'Log event unavailable'
                                            }}</code>
                                        </div>
                                    </div>
                                </article>
                            </div>
                            <div v-else class="empty-filter">
                                <span>⌕</span
                                ><strong>No matching groups</strong>
                                <p>
                                    Try a different search or severity filter.
                                </p>
                            </div>
                        </template>

                        <div v-else class="pre-run-state">
                            <div class="pre-run-icon"><span>✳</span></div>
                            <h3>Your sample logs are ready</h3>
                            <p>
                                Run triage to normalize identifiers, cluster
                                related events, and recommend a severity and
                                owner.
                            </p>
                            <button
                                class="text-run-button"
                                type="button"
                                :disabled="loading"
                                @click="runTriage"
                            >
                                {{
                                    loading
                                        ? 'Analyzing…'
                                        : 'Analyze 12 events →'
                                }}
                            </button>
                        </div>
                    </div>

                    <aside class="method-panel panel">
                        <div class="method-heading">
                            <div>
                                <div class="section-kicker">UNDER THE HOOD</div>
                                <h2>How triage works</h2>
                            </div>
                            <span class="method-spark">✳</span>
                        </div>
                        <p class="method-intro">
                            A transparent, three-step pipeline. AI assists with
                            prioritization—your log data stays yours.
                        </p>
                        <div class="pipeline">
                            <div class="pipeline-step">
                                <div class="step-marker marker-one">01</div>
                                <div>
                                    <strong>Normalize signatures</strong>
                                    <p>
                                        Strip request IDs, UUIDs, and noisy
                                        values to reveal the underlying pattern.
                                    </p>
                                </div>
                            </div>
                            <div class="pipeline-connector"></div>
                            <div class="pipeline-step">
                                <div class="step-marker marker-two">02</div>
                                <div>
                                    <strong>Cluster with TF-IDF</strong>
                                    <p>
                                        Nearest-neighbor similarity groups
                                        related log lines without a pre-trained
                                        model.
                                    </p>
                                </div>
                            </div>
                            <div class="pipeline-connector"></div>
                            <div class="pipeline-step">
                                <div class="step-marker marker-three">03</div>
                                <div>
                                    <strong>Recommend an owner</strong>
                                    <p>
                                        {{
                                            llmMode === 'Local model'
                                                ? 'Local rules assign severity and a suggested team. Gemini is optional.'
                                                : 'Gemini suggests severity, owner, and a concise rationale.'
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="privacy-note">
                            <span>◇</span>
                            <p>
                                <strong>Built for safe demos.</strong> This
                                workspace contains fictional logs only. LLM
                                requests have a 12-second timeout and
                                automatically fall back to local rules.
                            </p>
                        </div>
                        <a
                            class="readme-link"
                            href="https://github.com/oscarmmi/log-triage-desk#how-the-ai-design-works"
                            target="_blank"
                            rel="noreferrer"
                            >Explore the AI design <span>↗</span></a
                        >
                    </aside>
                </section>

                <section class="events-panel panel" id="events">
                    <div class="events-heading">
                        <div>
                            <div class="section-kicker">SOURCE DATA</div>
                            <h2>
                                Recent log events
                                <span class="heading-count">{{
                                    events.length
                                }}</span>
                            </h2>
                        </div>
                        <span class="events-caption"
                            >Fictional Harbor Desk sample data</span
                        >
                    </div>
                    <div class="events-table-wrap">
                        <table class="events-table">
                            <thead>
                                <tr>
                                    <th>TIME</th>
                                    <th>SOURCE</th>
                                    <th>MESSAGE</th>
                                    <th>STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="event in events.slice(0, 6)"
                                    :key="event.id"
                                >
                                    <td class="event-time">
                                        {{ formatTime(event.occurred_at) }}
                                    </td>
                                    <td>
                                        <span class="source-name"
                                            ><i></i>{{ event.source }}</span
                                        >
                                    </td>
                                    <td class="event-message">
                                        {{ event.message }}
                                    </td>
                                    <td>
                                        <span
                                            :class="[
                                                'event-status',
                                                event.message.includes('ERROR')
                                                    ? 'event-error'
                                                    : event.message.includes(
                                                            'WARN',
                                                        )
                                                      ? 'event-warn'
                                                      : 'event-info',
                                            ]"
                                            >{{
                                                event.message.includes('ERROR')
                                                    ? 'ERROR'
                                                    : event.message.includes(
                                                            'WARN',
                                                        )
                                                      ? 'WARN'
                                                      : 'INFO'
                                            }}</span
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <footer class="page-footer">
                    <span>© 2026 Harbor Desk · Fictional demo workspace</span
                    ><span
                        >Built with Laravel, Vue &amp; a little signal over
                        noise.</span
                    >
                </footer>
            </div>
        </main>
    </div>
</template>
