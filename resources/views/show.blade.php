<x-ai-tests::layout :title="$result->title().' · AI test results'">
    @push('styles')
        <style>
            .topbar { display: flex; align-items: center; justify-content: space-between; gap: 8px 16px; margin-bottom: 14px; }
            .back { display: inline-flex; align-items: center; gap: 6px; min-height: 28px; font-size: 14px; white-space: nowrap; }
            .back svg { width: 14px; height: 14px; }

            .pager { display: flex; align-items: stretch; min-width: 0; height: 32px; border: 1px solid var(--border-strong); border-radius: 8px; font-size: 13.5px; }
            .pager-link { display: inline-flex; align-items: center; gap: 7px; min-width: 32px; padding: 0 10px; color: var(--muted); }
            .pager-link:first-child { border-radius: 7px 0 0 7px; }
            .pager-link:last-child { border-radius: 0 7px 7px 0; }
            a.pager-link:hover { background: var(--surface); color: var(--text); text-decoration: none; }
            .pager-link:focus-visible { outline-offset: -2px; }
            .pager-link svg { flex: none; width: 14px; height: 14px; }
            .pager-link.is-disabled { justify-content: center; opacity: 0.4; }
            .pager-title { max-width: 22ch; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .pager-position { display: inline-flex; align-items: center; padding: 0 12px; border-inline: 1px solid var(--border); color: var(--text); font-weight: 500; white-space: nowrap; }
            .pager-dot { flex: none; width: 7px; height: 7px; border-radius: 50%; background: var(--meter-empty); }
            .pager-dot.is-passed { background: var(--passed-fill); }
            .pager-dot.is-failed { background: var(--failed-fill); }

            .pager-footer { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-top: 48px; }
            .pager-card { display: flex; flex-direction: column; gap: 2px; padding: 12px 16px; border: 1px solid var(--border-strong); border-radius: 12px; color: var(--text); }
            .pager-card:hover { background: var(--surface); text-decoration: none; }
            .pager-card-next { grid-column: 2; align-items: flex-end; text-align: right; }
            .pager-card-label { display: inline-flex; align-items: center; gap: 4px; color: var(--muted); font-size: 12.5px; font-weight: 600; }
            .pager-card-label svg { width: 12px; height: 12px; }
            .pager-card-title { display: inline-flex; align-items: baseline; gap: 8px; font-weight: 500; overflow-wrap: anywhere; }
            .pager-card-title .pager-dot { transform: translateY(-1px); }
            .pager-hint { grid-column: 1 / -1; margin: 4px 0 0; color: var(--muted); font-size: 12.5px; text-align: center; }
            .pager-hint kbd { display: inline-block; min-width: 18px; padding: 0 4px; border: 1px solid var(--border-strong); border-bottom-width: 2px; border-radius: 4px; font: 500 11.5px/16px var(--mono); text-align: center; }
            @media (hover: none) { .pager-hint { display: none; } }
            .result-header { padding-bottom: 24px; border-bottom: 1px solid var(--border); }
            .verdict { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 12px; margin: 12px 0 0; }
            .verdict-runs { font-weight: 600; font-variant-numeric: tabular-nums; }
            .facts { display: flex; flex-wrap: wrap; gap: 8px 28px; margin: 18px 0 0; font-size: 13.5px; }
            .facts div { min-width: 0; }
            .facts dt { color: var(--muted); font-size: 12.5px; }
            .facts dd { margin: 0; overflow-wrap: anywhere; }
            .facts .is-path { font-family: var(--mono); font-size: 12.5px; }

            .turn { padding-top: 32px; }
            .turn + .turn { margin-top: 16px; border-top: 1px solid var(--border); }
            .turn-header { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 12px; margin-bottom: 14px; }
            .turn-summary { color: var(--muted); font-size: 13.5px; }
            .turn-summary-partial { color: var(--partial); font-weight: 600; }
            .turn-summary-failed { color: var(--failed); font-weight: 600; }
            .turn-block { margin-top: 28px; }
            .turn-block > h3 { margin-bottom: 10px; }

            .prompt { margin: 0; padding: 12px 16px; background: var(--surface); border-radius: 10px; }
            .prompt-label { display: block; color: var(--muted); font-size: 12.5px; font-weight: 600; }
            .prompt p { margin: 2px 0 0; max-width: 75ch; white-space: pre-wrap; overflow-wrap: anywhere; }

            .checks { margin-top: 16px; }
            .checks td:last-child { width: 1%; }
            .checks .check-failed td:first-child, .checks .check-partial td:first-child { font-weight: 500; }

            .notes { list-style: none; margin: 0; padding: 0; }
            .note-group + .note-group { margin-top: 16px; }
            .note-check { margin: 0 0 6px; font-weight: 500; max-width: 75ch; }
            .note { display: grid; grid-template-columns: 56px minmax(0, 1fr); gap: 12px; padding: 6px 0; }
            .note p { margin: 0; max-width: 75ch; }
            .run-label { color: var(--muted); font-size: 13px; font-variant-numeric: tabular-nums; padding-top: 1px; white-space: nowrap; }
            .crash { margin: 0; padding: 10px 12px; background: var(--failed-background); border-radius: 8px; font-family: var(--mono); font-size: 12.5px; line-height: 1.5; white-space: pre-wrap; overflow-wrap: anywhere; }
            .unreadable { margin: 0 0 16px; }
            .unreadable p { margin: 0 0 8px; }

            .tablist { display: flex; gap: 2px; overflow-x: auto; border-bottom: 1px solid var(--border-strong); }
            .tablist[hidden] { display: none; }
            .tab { display: inline-flex; align-items: center; gap: 7px; margin: 0 0 -1px; padding: 8px 14px; border: 0; border-bottom: 2px solid transparent; background: none; color: var(--muted); font: inherit; font-size: 14px; font-weight: 500; white-space: nowrap; cursor: pointer; }
            .tab:hover { color: var(--text); }
            .tab[aria-selected="true"] { color: var(--text); border-bottom-color: var(--text); font-weight: 600; }
            .tab:focus-visible { outline-offset: -2px; }
            .tab-dot { width: 7px; height: 7px; border-radius: 50%; }
            .tab-passed .tab-dot { background: var(--passed-fill); }
            .tab-failed .tab-dot { background: var(--failed-fill); }
            .tab-unjudged .tab-dot { background: var(--muted); }

            .run { border: 1px solid var(--border-strong); border-radius: 12px; overflow: hidden; }
            .run + .run { margin-top: 16px; }
            .is-tabbed .run { margin-top: 12px; }
            .is-tabbed .run[hidden] { display: none; }
            .is-tabbed .run-header { display: none; }
            .run-header { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 12px; padding: 10px 16px; background: var(--surface); border-bottom: 1px solid var(--border); }
            .run-header h4 { margin: 0; font-size: 14px; font-weight: 600; }
            .run-section { padding: 12px 16px; border-bottom: 1px solid var(--border); font-size: 13.5px; }
            .run-section:last-child { border-bottom: 0; }
            .run-section > p { margin: 0; }
            .run-section-label { display: block; color: var(--muted); font-size: 12.5px; font-weight: 600; margin-bottom: 6px; }
            .tool-calls { margin: 0; padding: 0; list-style: none; }
            .tool-calls li { font-family: var(--mono); font-size: 12.5px; line-height: 1.55; overflow-wrap: anywhere; }
            .tool-calls li + li { margin-top: 4px; }
            .tool-name { font-weight: 600; }
            .tool-arguments, .tool-details { color: var(--muted); }
            .answer { padding: 16px; font-size: 15px; overflow-wrap: anywhere; }
            .answer > * { max-width: 75ch; }
            .answer pre, .answer table { max-width: none; }
            .answer table { display: block; overflow-x: auto; font-size: 14px; }
            .verdicts { list-style: none; margin: 0; padding: 0; }
            .verdicts li { display: grid; grid-template-columns: 14px minmax(0, 1fr); gap: 8px; padding: 4px 0; }
            .verdicts li::before { content: ""; width: 7px; height: 7px; margin-top: 7px; border-radius: 50%; }
            .verdicts .verdict-passed::before { background: var(--passed-fill); }
            .verdicts .verdict-failed::before { background: var(--failed-fill); }
            .verdicts p { margin: 0; max-width: 75ch; }
            .verdict-check { font-weight: 500; }
            .verdict-reason { color: var(--muted); }

            @media (max-width: 480px) {
                .note { grid-template-columns: 1fr; gap: 0; }
                .run-header, .run-section, .answer { padding-left: 12px; padding-right: 12px; }
                .tab { padding-left: 10px; padding-right: 10px; }
                .pager-footer { grid-template-columns: minmax(0, 1fr); }
                .pager-card-next { grid-column: 1; }
            }

            @media (max-width: 720px) {
                .pager-title, .pager-link .pager-dot { display: none; }
                .pager-link { justify-content: center; padding: 0; }
            }
        </style>
    @endpush

    <div class="topbar">
        <nav aria-label="Breadcrumb">
            <a class="back" href="{{ route('ai-tests.index') }}">
                <x-ai-tests::chevron direction="previous" />
                All results
            </a>
        </nav>

        @if ($navigation->total > 1)
            <nav class="pager" aria-label="Tests">
                @if ($navigation->previous)
                    <a
                        class="pager-link"
                        href="{{ route('ai-tests.show', $navigation->previous->slug) }}"
                        rel="prev"
                        aria-label="Previous test: {{ $navigation->previous->title() }}"
                        aria-keyshortcuts="k"
                        title="Previous test: {{ $navigation->previous->title() }} (K)"
                    >
                        <x-ai-tests::chevron direction="previous" />
                        <span class="pager-dot is-{{ $navigation->previous->status() }}" aria-hidden="true"></span>
                        <span class="pager-title">{{ $navigation->previous->title() }}</span>
                    </a>
                @else
                    <span class="pager-link is-disabled" aria-hidden="true">
                        <x-ai-tests::chevron direction="previous" />
                    </span>
                @endif

                <span class="pager-position numeric"><span class="visually-hidden">Test&nbsp;</span>{{ $navigation->position }} of {{ $navigation->total }}</span>

                @if ($navigation->next)
                    <a
                        class="pager-link"
                        href="{{ route('ai-tests.show', $navigation->next->slug) }}"
                        rel="next"
                        aria-label="Next test: {{ $navigation->next->title() }}"
                        aria-keyshortcuts="j"
                        title="Next test: {{ $navigation->next->title() }} (J)"
                    >
                        <span class="pager-title">{{ $navigation->next->title() }}</span>
                        <span class="pager-dot is-{{ $navigation->next->status() }}" aria-hidden="true"></span>
                        <x-ai-tests::chevron direction="next" />
                    </a>
                @else
                    <span class="pager-link is-disabled" aria-hidden="true">
                        <x-ai-tests::chevron direction="next" />
                    </span>
                @endif
            </nav>
        @endif
    </div>

    <header class="result-header">
        <h1>{{ $result->title() }}</h1>

        <p class="verdict">
            <x-ai-tests::status :status="$result->status()" />
            @if (is_numeric($result->get('passed_runs')) && is_numeric($result->get('runs')))
                <span class="verdict-runs">{{ $result->get('passed_runs') }} of {{ $result->get('runs') }} runs passed</span>
            @endif
            @if ($result->percentage('min_pass_rate'))
                <span class="muted numeric">min {{ $result->percentage('min_pass_rate') }}</span>
            @endif
            @if (is_numeric($result->get('unjudged_runs')))
                <span class="muted numeric">{{ $result->get('unjudged_runs') }} unjudged, left out of the pass rate</span>
            @endif
        </p>

        @if ($result->error !== null)
            <div class="unreadable">
                <p><strong>This result file could not be read.</strong> Fix or delete it, or run the test again.</p>
                <p class="crash">{{ $result->error }}</p>
            </div>
        @endif

        <dl class="facts">
            @foreach ($result->facts() as $label => $value)
                <div>
                    <dt>{{ $label }}</dt>
                    <dd @class(['is-path' => $label === 'Test file'])>{{ $value }}</dd>
                </div>
            @endforeach
            @if ($result->ranAt())
                <div>
                    <dt>Ran at</dt>
                    <dd><x-ai-tests::time :value="$result->ranAt()" /></dd>
                </div>
            @endif
        </dl>
    </header>

    @foreach ($turns as $turn)
        @php
            $selectedRun = $turn->selectedRun();
            $failureGroups = $turn->failureGroups();
            $crashedRuns = $turn->crashedRuns();
            $judgeErrorRuns = $turn->judgeErrorRuns();
        @endphp
        <section class="turn" id="turn-{{ $turn->number }}" aria-labelledby="turn-{{ $turn->number }}-heading">
            <div class="turn-header">
                <h2 id="turn-{{ $turn->number }}-heading">Turn {{ $turn->number }}</h2>
                @if ($turn->checks !== [])
                    <span @class(['turn-summary', 'numeric', 'turn-summary-'.$turn->checksStatus()])>{{ $turn->checksPassedInEveryRun() }} of {{ count($turn->checks) }} checks passed in every run</span>
                @endif
            </div>

            @if ($turn->prompt !== null)
                <div class="prompt">
                    <span class="prompt-label">User</span>
                    <p>{{ $turn->prompt }}</p>
                </div>
            @endif

            @if ($turn->checks !== [])
                <div class="table-scroll checks">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Check</th>
                                <th scope="col">Runs passed</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($turn->checks as $check)
                                <tr class="check-{{ $check->status() }}">
                                    <td>{!! $check->labelHtml() !!}</td>
                                    <td><x-ai-tests::runs :passed="$check->passedRuns()" :runs="$check->runCount()" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($failureGroups !== [])
                <div class="turn-block">
                    <h3>Failure reasons</h3>
                    <ul class="notes">
                        @foreach ($failureGroups as $group)
                            <li class="note-group">
                                <p class="note-check">{!! $group['checkHtml'] !!}</p>
                                <ul class="notes">
                                    @foreach ($group['notes'] as $note)
                                        <li class="note">
                                            <span class="run-label">Run {{ $note->run }}</span>
                                            <p>{{ $note->reason ?? 'Failed.' }}</p>
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($crashedRuns !== [])
                <div class="turn-block">
                    <h3>Crashes</h3>
                    <ul class="notes">
                        @foreach ($crashedRuns as $run)
                            <li class="note">
                                <span class="run-label">Run {{ $run->number }}</span>
                                <p class="crash">{{ $run->crash }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($judgeErrorRuns !== [])
                <div class="turn-block">
                    <h3>Judge errors</h3>
                    <ul class="notes">
                        @foreach ($judgeErrorRuns as $run)
                            <li class="note">
                                <span class="run-label">Run {{ $run->number }}</span>
                                <p class="crash">{{ $run->judgeError }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($turn->runs !== [])
                <section class="turn-block" aria-labelledby="turn-{{ $turn->number }}-runs" data-run-tabs>
                    <h3 id="turn-{{ $turn->number }}-runs">Runs</h3>

                    <div class="tablist" aria-labelledby="turn-{{ $turn->number }}-runs" hidden>
                        @foreach ($turn->runs as $run)
                            <button
                                type="button"
                                class="tab tab-{{ $run->status() }}"
                                id="turn-{{ $turn->number }}-run-{{ $run->number }}-tab"
                                aria-controls="turn-{{ $turn->number }}-run-{{ $run->number }}"
                                aria-label="Run {{ $run->number }}, {{ $run->status() }}"
                                @if ($run === $selectedRun) data-selected @endif
                            >
                                <span class="tab-dot" aria-hidden="true"></span>
                                Run {{ $run->number }}
                            </button>
                        @endforeach
                    </div>

                    @foreach ($turn->runs as $run)
                        @php($answerHtml = $run->answerHtml())
                        <section class="run" id="turn-{{ $turn->number }}-run-{{ $run->number }}" aria-label="Run {{ $run->number }}, {{ $run->status() }}">
                            <div class="run-header">
                                <h4>Run {{ $run->number }}</h4>
                                <x-ai-tests::status :status="$run->status()" />
                            </div>

                            @if ($run->notReached)
                                <div class="run-section">
                                    <p class="muted">This run crashed in an earlier turn.</p>
                                </div>
                            @else
                                @if ($run->crash !== null)
                                    <div class="run-section">
                                        <span class="run-section-label">Crash</span>
                                        <p class="crash">{{ $run->crash }}</p>
                                    </div>
                                @endif

                                @if ($run->judgeError !== null)
                                    <div class="run-section">
                                        <span class="run-section-label">Judge error</span>
                                        <p class="crash">{{ $run->judgeError }}</p>
                                    </div>
                                @endif

                                @if ($run->showsTranscript())
                                    <div class="run-section">
                                        <span class="run-section-label">Tool calls</span>
                                        @if ($run->toolCalls === [])
                                            <span class="muted">None</span>
                                        @else
                                            <ol class="tool-calls">
                                                @foreach ($run->toolCalls as $toolCall)
                                                    <li>
                                                        <span class="tool-name">{{ $toolCall->name }}</span>
                                                        @if ($toolCall->argumentList() !== '')
                                                            <span class="tool-arguments">({{ $toolCall->argumentList() }})</span>
                                                        @endif
                                                        @if ($toolCall->details() !== [])
                                                            <span class="tool-details">{{ implode(', ', $toolCall->details()) }}</span>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ol>
                                        @endif
                                    </div>

                                    <div class="answer prose run-section">
                                        @if ($answerHtml !== null)
                                            {!! $answerHtml !!}
                                        @else
                                            <p class="muted">No answer text.</p>
                                        @endif
                                    </div>
                                @endif

                                @if ($run->verdicts !== [])
                                    <div class="run-section">
                                        <span class="run-section-label">Verdicts</span>
                                        <ul class="verdicts">
                                            @foreach ($run->verdicts as $verdict)
                                                <li class="verdict-{{ $verdict->passed ? 'passed' : 'failed' }}">
                                                    <div>
                                                        <p class="verdict-check"><span class="visually-hidden">{{ $verdict->passed ? 'Passed' : 'Failed' }}: </span>{!! $verdict->checkHtml() !!}</p>
                                                        @if ($verdict->reason !== null)
                                                            <p class="verdict-reason">{{ $verdict->reason }}</p>
                                                        @endif
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            @endif
                        </section>
                    @endforeach
                </section>
            @endif
        </section>
    @endforeach

    @if ($navigation->previous || $navigation->next)
        <nav class="pager-footer" aria-label="Previous and next test">
            @if ($navigation->previous)
                <a class="pager-card" href="{{ route('ai-tests.show', $navigation->previous->slug) }}" rel="prev">
                    <span class="pager-card-label">
                        <x-ai-tests::chevron direction="previous" />
                        Previous test
                    </span>
                    <span class="pager-card-title">
                        <span class="pager-dot is-{{ $navigation->previous->status() }}" aria-hidden="true"></span>
                        {{ $navigation->previous->title() }}
                    </span>
                </a>
            @endif

            @if ($navigation->next)
                <a class="pager-card pager-card-next" href="{{ route('ai-tests.show', $navigation->next->slug) }}" rel="next">
                    <span class="pager-card-label">
                        Next test
                        <x-ai-tests::chevron direction="next" />
                    </span>
                    <span class="pager-card-title">
                        {{ $navigation->next->title() }}
                        <span class="pager-dot is-{{ $navigation->next->status() }}" aria-hidden="true"></span>
                    </span>
                </a>
            @endif

            <p class="pager-hint">Press <kbd>J</kbd> for the next test and <kbd>K</kbd> for the previous one.</p>
        </nav>
    @endif

    @push('scripts')
        <script>
            document.addEventListener('keydown', (event) => {
                const relations = { j: 'next', k: 'prev' };
                const isTyping = event.target.closest('input, textarea, select, [contenteditable]:not([contenteditable="false"])');

                if (! relations[event.key] || event.defaultPrevented || event.isComposing || isTyping) {
                    return;
                }

                if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
                    return;
                }

                const link = document.querySelector(`.pager a[rel="${relations[event.key]}"]`);

                if (link) {
                    event.preventDefault();
                    link.click();
                }
            });
        </script>
    @endpush

    @push('scripts')
        <script>
            document.querySelectorAll('[data-run-tabs]').forEach((container) => {
                const tablist = container.querySelector('.tablist');
                const tabs = Array.from(tablist.querySelectorAll('.tab'));
                const panelOf = (tab) => document.getElementById(tab.getAttribute('aria-controls'));

                const select = (selected, focus) => {
                    tabs.forEach((tab) => {
                        const isSelected = tab === selected;
                        tab.setAttribute('aria-selected', String(isSelected));
                        tab.tabIndex = isSelected ? 0 : -1;
                        panelOf(tab).hidden = ! isSelected;
                    });

                    if (focus) {
                        selected.focus();
                    }
                };

                tablist.setAttribute('role', 'tablist');
                tabs.forEach((tab) => {
                    const panel = panelOf(tab);
                    tab.setAttribute('role', 'tab');
                    panel.setAttribute('role', 'tabpanel');
                    panel.setAttribute('aria-labelledby', tab.id);
                    panel.removeAttribute('aria-label');
                    panel.tabIndex = 0;

                    tab.addEventListener('click', () => select(tab, false));
                    tab.addEventListener('keydown', (event) => {
                        const index = tabs.indexOf(tab);
                        const target = {
                            ArrowRight: tabs[(index + 1) % tabs.length],
                            ArrowLeft: tabs[(index - 1 + tabs.length) % tabs.length],
                            Home: tabs[0],
                            End: tabs[tabs.length - 1],
                        }[event.key];

                        if (target) {
                            event.preventDefault();
                            select(target, true);
                        }
                    });
                });

                container.classList.add('is-tabbed');
                tablist.hidden = false;
                select(tabs.find((tab) => tab.hasAttribute('data-selected')) ?? tabs[0], false);
            });
        </script>
    @endpush
</x-ai-tests::layout>
