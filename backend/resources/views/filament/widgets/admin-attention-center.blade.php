<x-filament-widgets::widget>
    <style>
        .cardora-admin-attention {
            --cardora-border: #dbe4f0;
            --cardora-slate: #0f172a;
            --cardora-muted: #64748b;
            --cardora-danger: #dc2626;
            --cardora-danger-soft: #fff1f2;
            --cardora-warning: #d97706;
            --cardora-warning-soft: #fff7ed;
            --cardora-success: #059669;
            --cardora-success-soft: #ecfdf5;
            --cardora-info: #2563eb;
            --cardora-info-soft: #eff6ff;
        }

        .cardora-admin-attention .cardora-attention-hero {
            overflow: hidden;
            border-radius: 28px;
            border: 1px solid rgba(220, 38, 38, 0.14);
            background:
                radial-gradient(circle at top right, rgba(245, 158, 11, 0.12), transparent 28%),
                radial-gradient(circle at bottom left, rgba(220, 38, 38, 0.08), transparent 34%),
                linear-gradient(145deg, #fffdfd 0%, #fff8f3 100%);
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
        }

        .cardora-admin-attention .cardora-attention-card {
            border-radius: 24px;
            border: 1px solid var(--cardora-border);
            background: #fff;
            box-shadow: 0 16px 38px rgba(15, 23, 42, 0.06);
        }

        .cardora-admin-attention .cardora-attention-card[data-tone="danger"] {
            border-color: rgba(220, 38, 38, 0.14);
            background: linear-gradient(180deg, #fff 0%, var(--cardora-danger-soft) 100%);
        }

        .cardora-admin-attention .cardora-attention-card[data-tone="warning"] {
            border-color: rgba(217, 119, 6, 0.16);
            background: linear-gradient(180deg, #fff 0%, var(--cardora-warning-soft) 100%);
        }

        .cardora-admin-attention .cardora-attention-card[data-tone="success"] {
            border-color: rgba(5, 150, 105, 0.16);
            background: linear-gradient(180deg, #fff 0%, var(--cardora-success-soft) 100%);
        }

        .cardora-admin-attention .cardora-attention-card[data-tone="info"] {
            border-color: rgba(37, 99, 235, 0.16);
            background: linear-gradient(180deg, #fff 0%, var(--cardora-info-soft) 100%);
        }

        .cardora-admin-attention .cardora-tone-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0.68rem 1rem;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .cardora-admin-attention .cardora-tone-chip[data-tone="danger"] {
            background: var(--cardora-danger);
            color: #fff;
        }

        .cardora-admin-attention .cardora-tone-chip[data-tone="warning"] {
            background: #f59e0b;
            color: #fff;
        }

        .cardora-admin-attention .cardora-tone-chip[data-tone="success"] {
            background: var(--cardora-success);
            color: #fff;
        }

        .cardora-admin-attention .cardora-tone-chip[data-tone="info"] {
            background: var(--cardora-info);
            color: #fff;
        }

        .cardora-admin-attention .cardora-count-box {
            display: inline-flex;
            min-width: 10rem;
            align-items: center;
            justify-content: center;
            border-radius: 22px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            background: rgba(255, 255, 255, 0.94);
            padding: 1rem 1.15rem;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.72);
        }

        .cardora-admin-attention .cardora-icon-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 3rem;
            width: 3rem;
            border-radius: 18px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            background: rgba(255, 255, 255, 0.94);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.06);
        }

        .cardora-admin-attention .cardora-icon-badge[data-tone="danger"] { color: var(--cardora-danger); }
        .cardora-admin-attention .cardora-icon-badge[data-tone="warning"] { color: var(--cardora-warning); }
        .cardora-admin-attention .cardora-icon-badge[data-tone="success"] { color: var(--cardora-success); }
        .cardora-admin-attention .cardora-icon-badge[data-tone="info"] { color: var(--cardora-info); }

        .cardora-admin-attention .cardora-item-link {
            display: block;
            border-radius: 18px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            background: rgba(255, 255, 255, 0.92);
            padding: 0.95rem 1rem;
            transition: transform 140ms ease, box-shadow 140ms ease, border-color 140ms ease;
        }

        .cardora-admin-attention .cardora-item-link:hover {
            transform: translateY(-1px);
            border-color: rgba(15, 23, 42, 0.16);
            box-shadow: 0 14px 26px rgba(15, 23, 42, 0.08);
        }
    </style>

    <div class="cardora-admin-attention space-y-6">
        <div class="cardora-attention-hero p-7 sm:p-8">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-center xl:justify-between">
                <div class="max-w-3xl">
                    <p class="text-[11px] font-bold uppercase tracking-[0.36em] text-slate-500">
                        Admin Attention Center
                    </p>
                    <h2 class="mt-4 text-3xl font-black tracking-tight text-slate-950 sm:text-[2.15rem]">
                        {{ $headline }}
                    </h2>
                    <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600">
                        {{ $subheadline }}
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="cardora-count-box text-center">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-[0.26em] text-slate-500">
                                Live queues
                            </p>
                            <p class="mt-2 text-4xl font-black text-slate-950">
                                {{ $headlineCount }}
                            </p>
                        </div>
                    </div>

                    <div class="cardora-count-box text-center">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-[0.26em] text-slate-500">
                                Status
                            </p>
                            <div class="mt-3">
                                <span class="cardora-tone-chip" data-tone="{{ $hasUrgentItems ? 'danger' : 'success' }}">
                                    {{ $hasUrgentItems ? 'Needs action now' : 'All clear' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-5 xl:grid-cols-2">
            @foreach ($groups as $group)
                <section class="cardora-attention-card p-5 sm:p-6" data-tone="{{ $group['tone'] }}">
                    <div class="flex flex-col gap-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex items-start gap-4">
                                    <div class="cardora-icon-badge shrink-0" data-tone="{{ $group['tone'] }}">
                                        <x-filament::icon :icon="$group['icon']" class="h-5 w-5" />
                                    </div>

                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-3">
                                            <h3 class="text-lg font-bold text-slate-950">{{ $group['label'] }}</h3>
                                            <span class="cardora-tone-chip" data-tone="{{ $group['tone'] }}">
                                                {{ $group['count'] }} item{{ (int) $group['count'] === 1 ? '' : 's' }}
                                            </span>
                                        </div>
                                        <p class="mt-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                                            {{ $group['meta'] }}
                                        </p>
                                    </div>
                                </div>

                                <p class="mt-4 text-sm leading-7 text-slate-600">
                                    {{ $group['description'] }}
                                </p>
                            </div>

                            <div class="shrink-0">
                                <a
                                    href="{{ $group['url'] }}"
                                    class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
                                >
                                    {{ $group['cta'] }}
                                </a>
                            </div>
                        </div>

                        @if (filled($group['items']))
                            <div class="space-y-3">
                                @foreach ($group['items'] as $item)
                                    <a href="{{ $item['url'] }}" class="cardora-item-link">
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-slate-950">
                                                    {{ $item['title'] }}
                                                </p>
                                                <p class="mt-1 text-xs leading-6 text-slate-600">
                                                    {{ $item['subtitle'] }}
                                                </p>
                                            </div>

                                            <span class="shrink-0 rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                                                {{ $item['time'] }}
                                            </span>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
