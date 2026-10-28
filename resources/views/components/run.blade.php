@props(['run', 'turnNumber'])

@php($answerHtml = $run->answerHtml())
<section class="run" id="turn-{{ $turnNumber }}-run-{{ $run->number }}" aria-label="Run {{ $run->number }}, {{ $run->status()->value }}">
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
