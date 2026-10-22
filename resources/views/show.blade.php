@php
    $facts = array_filter([
        'Model' => $result->get('model'),
        'Judge' => $result->get('judge_model'),
        'Seeder' => $result->get('seeder'),
        'Test file' => $result->get('test_file'),
    ]);
@endphp

<x-ai-tests::layout :title="$result->title().' · AI test results'">
    @push('styles')
        <style>
            .back { display: inline-flex; align-items: center; gap: 6px; min-height: 28px; margin-bottom: 14px; font-size: 14px; }
            .back svg { width: 14px; height: 14px; }
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
            .prompt p { margin: 2px 0 0; max-width: 75ch; }

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

            .example { border: 1px solid var(--border-strong); border-radius: 12px; overflow: hidden; }
            .example-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 6px 12px; padding: 10px 16px; background: var(--surface); border-bottom: 1px solid var(--border); }
            .example-meta { display: inline-flex; align-items: center; gap: 10px; color: var(--muted); font-size: 13.5px; }
            .tools { padding: 12px 16px; border-bottom: 1px solid var(--border); font-size: 13.5px; }
            .tools-label { display: block; color: var(--muted); font-size: 12.5px; font-weight: 600; margin-bottom: 6px; }
            .tool-calls { margin: 0; padding: 0; list-style: none; }
            .tool-calls li { font-family: var(--mono); font-size: 12.5px; line-height: 1.55; overflow-wrap: anywhere; }
            .tool-calls li + li { margin-top: 4px; }
            .tool-name { font-weight: 600; }
            .tool-arguments { color: var(--muted); }
            .answer { padding: 16px; overflow-wrap: anywhere; }
            .answer > * { max-width: 75ch; }
            .answer pre, .answer table { max-width: none; }
            .answer table, .fallback table { display: block; overflow-x: auto; font-size: 14px; }

            .fallback > h1:first-child { display: none; }

            @media (max-width: 480px) {
                .note { grid-template-columns: 1fr; gap: 0; }
                .example-header, .tools, .answer { padding-left: 12px; padding-right: 12px; }
            }
        </style>
    @endpush

    <nav aria-label="Breadcrumb">
        <a class="back" href="{{ route('ai-tests.index') }}">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 3 5 8l5 5"/></svg>
            All results
        </a>
    </nav>

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
        </p>

        <dl class="facts">
            @foreach ($facts as $label => $value)
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

    @if ($turns === null)
        <div class="prose fallback turn">{!! $html !!}</div>
    @else
        @foreach ($turns as $turn)
            <section class="turn" id="turn-{{ $turn->number }}" aria-labelledby="turn-{{ $turn->number }}-heading">
                <div class="turn-header">
                    <h2 id="turn-{{ $turn->number }}-heading">Turn {{ $turn->number }}</h2>
                    @if ($turn->checks !== [])
                        <span @class(['turn-summary', 'numeric', 'turn-summary-'.$turn->checksStatus()])>{{ count($turn->checks) - $turn->failingChecks() }} of {{ count($turn->checks) }} checks passed in every run</span>
                    @endif
                </div>

                @if ($turn->promptHtml !== null)
                    <div class="prompt">
                        <span class="prompt-label">User</span>
                        <p>{!! $turn->promptHtml !!}</p>
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
                                        <td>{!! $check->labelHtml !!}</td>
                                        <td><x-ai-tests::runs :passed="$check->passed" :runs="$check->runs" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($turn->extraHtml !== null)
                    <div class="prose turn-block">{!! $turn->extraHtml !!}</div>
                @endif

                @if ($turn->judgeNotes !== [])
                    <div class="turn-block">
                        <h3>Judge notes</h3>
                        <ul class="notes">
                            @foreach ($turn->judgeNoteGroups() as $group)
                                <li class="note-group">
                                    @if ($group['checkHtml'] !== null)
                                        <p class="note-check">{!! $group['checkHtml'] !!}</p>
                                    @endif
                                    <ul class="notes">
                                        @foreach ($group['notes'] as $note)
                                            <li class="note">
                                                <span class="run-label">{{ $note->run !== null ? "Run {$note->run}" : '' }}</span>
                                                <p>{!! $note->html !!}</p>
                                            </li>
                                        @endforeach
                                    </ul>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($turn->crashes !== [])
                    <div class="turn-block">
                        <h3>Crashes</h3>
                        <ul class="notes">
                            @foreach ($turn->crashes as $crash)
                                <li class="note">
                                    <span class="run-label">{{ $crash->run !== null ? "Run {$crash->run}" : '' }}</span>
                                    <p class="crash">{!! $crash->html !!}</p>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($turn->hasExample())
                    <section class="example turn-block" aria-labelledby="turn-{{ $turn->number }}-example">
                        <div class="example-header">
                            <h3 id="turn-{{ $turn->number }}-example">Example answer</h3>
                            <span class="example-meta">
                                @if ($turn->exampleRun() !== null)
                                    <span class="numeric">Run {{ $turn->exampleRun() }}</span>
                                @endif
                                @if ($turn->exampleStatus() !== null)
                                    <x-ai-tests::status :status="$turn->exampleStatus()" />
                                @endif
                            </span>
                        </div>

                        @if ($turn->toolCalls !== null)
                            <div class="tools">
                                <span class="tools-label">Tool calls</span>
                                @if ($turn->toolCalls === [])
                                    <span class="muted">None</span>
                                @else
                                    <ol class="tool-calls">
                                        @foreach ($turn->toolCalls as $toolCall)
                                            <li><span class="tool-name">{{ $toolCall['name'] }}</span>@if ($toolCall['arguments'] !== null) <span class="tool-arguments">({{ $toolCall['arguments'] }})</span>@endif</li>
                                        @endforeach
                                    </ol>
                                @endif
                            </div>
                        @endif

                        <div class="answer prose">
                            @if ($turn->answerHtml !== null)
                                {!! $turn->answerHtml !!}
                            @else
                                <p class="muted">No answer text.</p>
                            @endif
                        </div>
                    </section>
                @endif
            </section>
        @endforeach
    @endif
</x-ai-tests::layout>
