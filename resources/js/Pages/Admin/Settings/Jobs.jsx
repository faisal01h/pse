import Authenticated from '@/Layouts/Authenticated';
import SettingsNav from '@/Components/SettingsNav';
import { Head, Link, usePage } from '@inertiajs/inertia-react';
import { Inertia } from '@inertiajs/inertia';
import { useEffect, useState } from 'react';

const STATUSES = [
    { key: 'running', label: 'Running', hint: 'In progress right now' },
    { key: 'queued', label: 'Queued', hint: 'Waiting for a worker' },
    { key: 'finished', label: 'Finished', hint: 'Completed without error' },
    { key: 'failed', label: 'Failed', hint: 'Stopped with an exception' },
];

const DOT = {
    queued: 'bg-sky-700',
    running: 'bg-amber-500 animate-pulse',
    finished: 'bg-emerald-700',
    failed: 'bg-red-700',
};

function formatWhen(value) {
    if (!value) {
        return '—';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }
    return date.toLocaleString('en-GB', { hour12: false });
}

function formatDuration(ms) {
    if (ms == null) {
        return '—';
    }
    if (ms < 1000) {
        return `${ms} ms`;
    }
    const seconds = ms / 1000;
    if (seconds < 60) {
        return `${seconds.toFixed(2)} s`;
    }
    const minutes = Math.floor(seconds / 60);
    const rest = Math.round(seconds % 60);
    return `${minutes}m ${rest}s`;
}

function elapsedSince(iso, now) {
    if (!iso) {
        return '—';
    }
    const start = new Date(iso).getTime();
    if (Number.isNaN(start)) {
        return '—';
    }
    return formatDuration(Math.max(0, now - start));
}

function summaryEntries(summary) {
    if (!summary || typeof summary !== 'object') {
        return [];
    }
    return Object.entries(summary).map(([key, value]) => {
        if (value === null || value === undefined) {
            return [key, '—'];
        }
        if (typeof value === 'object') {
            return [key, JSON.stringify(value)];
        }
        return [key, String(value)];
    });
}

export default function Jobs({ auth, jobs = [], counts = {}, filters = {}, connection = 'sync' }) {
    const { flash } = usePage().props;
    const [live, setLive] = useState(true);
    const [openId, setOpenId] = useState(null);
    const [term, setTerm] = useState(filters.q || '');
    const [now, setNow] = useState(Date.now());
    const running = counts.running || 0;

    useEffect(() => {
        const timer = setInterval(() => setNow(Date.now()), 1000);
        return () => clearInterval(timer);
    }, []);

    useEffect(() => {
        if (!live) {
            return undefined;
        }
        const timer = setInterval(() => {
            Inertia.reload({ only: ['jobs', 'counts'], preserveScroll: true, preserveState: true });
        }, 3000);
        return () => clearInterval(timer);
    }, [live]);

    function applyFilters(next) {
        Inertia.get(route('admin.settings.jobs'), {
            status: next.status || undefined,
            q: next.q || undefined,
        }, { preserveState: true, preserveScroll: true });
    }

    return (
        <Authenticated wide auth={auth} header={<h1 className="text-4xl font-bold leading-tight">Settings</h1>}>
            <Head title="Job Monitor" />
            <p className="max-w-2xl">
                Watch queued work as it runs, finishes, or fails. The list refreshes on its own while live mode is on.
            </p>
            <SettingsNav />

            {flash?.success ? <p className="mt-4 border-l-8 border-brand bg-canvas px-4 py-3 font-bold">{flash.success}</p> : null}
            {flash?.error ? <p className="mt-4 border-l-8 border-red-700 bg-canvas px-4 py-3 font-bold">{flash.error}</p> : null}

            <div className="mt-6 flex flex-wrap items-center justify-between gap-3 border-2 border-ink bg-white px-4 py-3">
                <p className="text-sm">
                    Queue connection: <span className="font-bold">{connection}</span>
                    {connection === 'sync' ? ' · jobs finish inside the request that dispatched them.' : ' · a worker must be running to pick up queued jobs.'}
                </p>
                <button
                    type="button"
                    onClick={() => setLive((value) => !value)}
                    className="inline-flex items-center gap-2 border-2 border-ink px-3 py-1 text-sm font-bold"
                >
                    <span className={`inline-block h-2.5 w-2.5 rounded-full ${live ? 'bg-emerald-600 animate-pulse' : 'bg-ink/30'}`} />
                    {live ? 'Live' : 'Paused'}
                </button>
            </div>

            <div className="mt-4 grid gap-3 sm:grid-cols-4">
                {STATUSES.map((item) => {
                    const active = filters.status === item.key;
                    const total = counts[item.key] || 0;
                    return (
                        <button
                            key={item.key}
                            type="button"
                            onClick={() => applyFilters({ status: active ? null : item.key, q: term })}
                            className={`border-2 px-4 py-3 text-left ${active ? 'border-brand bg-white' : 'border-ink bg-canvas hover:bg-white'}`}
                        >
                            <span className="flex items-center gap-2 text-sm font-bold uppercase tracking-wide">
                                <span className={`inline-block h-2.5 w-2.5 rounded-full ${DOT[item.key]}`} />
                                {item.label}
                            </span>
                            <span className="mt-1 block text-3xl font-bold">{total}</span>
                            <span className="text-sm text-muted">{item.hint}</span>
                        </button>
                    );
                })}
            </div>

            <form
                className="mt-4 flex flex-wrap items-end gap-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    applyFilters({ status: filters.status, q: term.trim() });
                }}
            >
                <label className="text-sm">
                    <span className="mb-1 block font-bold">Find a job</span>
                    <input
                        value={term}
                        onChange={(event) => setTerm(event.target.value)}
                        placeholder="Class, queue, or id"
                        className="w-72 border-2 border-ink px-2 py-1.5"
                    />
                </label>
                <button className="border-2 border-ink bg-ink px-3 py-1.5 text-sm font-bold text-white">Search</button>
                {filters.status || filters.q ? (
                    <button
                        type="button"
                        className="px-3 py-1.5 text-sm font-bold underline"
                        onClick={() => {
                            setTerm('');
                            applyFilters({ status: null, q: '' });
                        }}
                    >
                        Clear filters
                    </button>
                ) : null}
                <Link
                    href={route('admin.settings.jobs.clear')}
                    method="post"
                    as="button"
                    preserveScroll
                    className="ml-auto text-sm font-bold underline"
                    onClick={(event) => {
                        if ((counts.finished || 0) === 0 || !window.confirm('Remove every finished job from this monitor?')) {
                            event.preventDefault();
                        }
                    }}
                >
                    Clear finished
                </Link>
            </form>

            <ul className="mt-6 flex flex-col gap-3">
                {jobs.length === 0 ? (
                    <li className="border-2 border-dashed border-ink/40 px-4 py-8 text-center text-muted">
                        {running > 0 ? 'Jobs are running.' : 'No jobs in this view yet. Dispatched work shows up here as queued, running, finished, or failed.'}
                    </li>
                ) : jobs.map((job) => {
                    const open = openId === job.id;
                    const entries = summaryEntries(job.summary);
                    return (
                        <li key={job.id} className="border-2 border-ink bg-white">
                            <button
                                type="button"
                                className="flex w-full items-start gap-3 px-4 py-3 text-left"
                                onClick={() => setOpenId(open ? null : job.id)}
                                aria-expanded={open}
                            >
                                <span className={`mt-1.5 inline-block h-3 w-3 shrink-0 rounded-full ${DOT[job.status] || 'bg-ink'}`} />
                                <span className="min-w-0 flex-1">
                                    <span className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                        <span className="text-lg font-bold">{job.name}</span>
                                        <span className="text-sm font-bold uppercase tracking-wide text-muted">{job.status}</span>
                                    </span>
                                    <span className="mt-1 block text-sm text-muted">
                                        {job.queue} · {job.connection}
                                        {' · '}
                                        {job.status === 'running'
                                            ? `running for ${elapsedSince(job.started_at, now)}`
                                            : job.status === 'queued'
                                                ? `queued ${formatWhen(job.queued_at)}`
                                                : `finished ${formatWhen(job.finished_at)} · ${formatDuration(job.duration_ms)}`}
                                    </span>
                                </span>
                                <span className="text-sm font-bold text-muted">{open ? 'Hide' : 'Details'}</span>
                            </button>
                            {open ? (
                                <div className="border-t-2 border-ink px-4 py-4">
                                    <dl className="grid gap-3 sm:grid-cols-2">
                                        <Detail label="Class">{job.class}</Detail>
                                        <Detail label="Job id">{job.uuid}</Detail>
                                        <Detail label="Connection">{job.connection}</Detail>
                                        <Detail label="Queue">{job.queue}</Detail>
                                        <Detail label="Attempts">{job.max_tries ? `${job.attempts} of ${job.max_tries}` : String(job.attempts)}</Detail>
                                        <Detail label="Timeout">{job.timeout ? `${job.timeout}s` : '—'}</Detail>
                                        <Detail label="Queued">{formatWhen(job.queued_at)}</Detail>
                                        <Detail label="Started">{formatWhen(job.started_at)}</Detail>
                                        <Detail label="Finished">{formatWhen(job.finished_at)}</Detail>
                                        <Detail label="Duration">{job.status === 'running' ? elapsedSince(job.started_at, now) : formatDuration(job.duration_ms)}</Detail>
                                    </dl>
                                    <h2 className="mt-4 text-sm font-bold uppercase tracking-wide text-muted">Arguments</h2>
                                    {entries.length === 0 ? (
                                        <p className="mt-1 text-sm text-muted">This job was dispatched without public arguments.</p>
                                    ) : (
                                        <dl className="mt-2 border-2 border-ink/20">
                                            {entries.map(([key, value]) => (
                                                <div key={key} className="grid gap-1 border-b border-ink/15 px-3 py-2 last:border-b-0 sm:grid-cols-[10rem_1fr]">
                                                    <dt className="font-bold">{key}</dt>
                                                    <dd className="break-words">{value}</dd>
                                                </div>
                                            ))}
                                        </dl>
                                    )}
                                    {job.exception ? (
                                        <>
                                            <h2 className="mt-4 text-sm font-bold uppercase tracking-wide text-red-800">Exception</h2>
                                            <pre className="mt-2 max-h-80 overflow-auto whitespace-pre-wrap border-2 border-red-800 bg-canvas p-3 text-sm">{job.exception}</pre>
                                        </>
                                    ) : null}
                                    <div className="mt-4 flex flex-wrap gap-3">
                                        {job.status === 'failed' ? (
                                            <Link
                                                href={route('admin.settings.jobs.retry', job.id)}
                                                method="post"
                                                as="button"
                                                preserveScroll
                                                className="border-2 border-ink bg-ink px-3 py-1.5 text-sm font-bold text-white"
                                            >
                                                Retry
                                            </Link>
                                        ) : null}
                                        <Link
                                            href={route('admin.settings.jobs.destroy', job.id)}
                                            method="delete"
                                            as="button"
                                            preserveScroll
                                            className="text-sm font-bold underline"
                                            onClick={(event) => {
                                                if (!window.confirm('Remove this run from the monitor?')) {
                                                    event.preventDefault();
                                                }
                                            }}
                                        >
                                            Remove from monitor
                                        </Link>
                                    </div>
                                </div>
                            ) : null}
                        </li>
                    );
                })}
            </ul>
        </Authenticated>
    );
}

function Detail({ label, children }) {
    return (
        <div>
            <dt className="text-sm font-bold uppercase tracking-wide text-muted">{label}</dt>
            <dd className="break-all">{children}</dd>
        </div>
    );
}
