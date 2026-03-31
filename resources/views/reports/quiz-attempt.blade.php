<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a2e; background: #fff; }

  /* ── Header ── */
  .header-table { width: 100%; background: #1e293b; }
  .header-table td { padding: 16px 20px; color: #fff; vertical-align: middle; }
  .header-title { font-size: 20px; font-weight: 700; letter-spacing: 1px; }
  .header-sub { font-size: 11px; opacity: 0.7; margin-top: 3px; }
  .header-right { text-align: right; font-size: 10px; opacity: 0.8; }
  .header-right strong { font-size: 13px; display: block; margin-bottom: 3px; }

  /* ── Result Banner ── */
  .banner-passed { background: #dcfce7; border-left: 6px solid #16a34a; padding: 12px 20px; }
  .banner-failed  { background: #fee2e2; border-left: 6px solid #dc2626; padding: 12px 20px; }
  .banner-table { width: 100%; }
  .verdict-passed { font-size: 22px; font-weight: 800; color: #16a34a; }
  .verdict-failed  { font-size: 22px; font-weight: 800; color: #dc2626; }
  .score-big { font-size: 30px; font-weight: 800; color: #1e293b; }
  .score-pct { font-size: 13px; color: #6b7280; }
  .pass-mark { font-size: 10px; color: #6b7280; margin-top: 3px; }

  /* ── Section ── */
  .section { margin: 14px 20px 0; }
  .section-title {
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 1px; color: #6b7280;
    border-bottom: 2px solid #e2e8f0; padding-bottom: 4px; margin-bottom: 10px;
  }

  /* ── Info Grid ── */
  .info-table { width: 100%; border-collapse: collapse; }
  .info-table td { padding: 6px 8px; vertical-align: top; width: 25%; }
  .info-cell { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 7px 10px; }
  .info-label { font-size: 9px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
  .info-value { font-size: 12px; font-weight: 700; color: #0f172a; }

  /* ── Stat Boxes ── */
  .stats-table { width: 100%; border-collapse: separate; border-spacing: 6px; }
  .stat-cell { text-align: center; padding: 10px 6px; border-radius: 6px; }
  .stat-num { font-size: 24px; font-weight: 800; }
  .stat-lbl { font-size: 9px; text-transform: uppercase; margin-top: 2px; letter-spacing: 0.5px; }
  .stat-blue   { background: #eff6ff; color: #1d4ed8; }
  .stat-green  { background: #f0fdf4; color: #16a34a; }
  .stat-red    { background: #fef2f2; color: #dc2626; }
  .stat-yellow { background: #fefce8; color: #ca8a04; }
  .stat-purple { background: #faf5ff; color: #7c3aed; }

  /* ── Breakdown Table ── */
  .data-table { width: 100%; border-collapse: collapse; }
  .data-table th {
    background: #f1f5f9; text-align: left;
    padding: 7px 10px; font-size: 10px;
    text-transform: uppercase; color: #64748b;
    border-bottom: 2px solid #e2e8f0;
  }
  .data-table td { padding: 7px 10px; border-bottom: 1px solid #f1f5f9; font-size: 11px; }
  .data-table tr:last-child td { border-bottom: none; }
  .data-table tr:nth-child(even) td { background: #f8fafc; }

  /* ── Difficulty Badge ── */
  .badge { font-size: 9px; padding: 2px 8px; border-radius: 999px; font-weight: 700; }
  .badge-easy   { background: #dcfce7; color: #15803d; }
  .badge-medium { background: #fef9c3; color: #a16207; }
  .badge-hard   { background: #fee2e2; color: #b91c1c; }
  .badge-correct   { background: #dcfce7; color: #15803d; }
  .badge-incorrect { background: #fee2e2; color: #b91c1c; }
  .badge-skipped   { background: #f1f5f9; color: #64748b; }
  .badge-marks  { background: #eff6ff; color: #1d4ed8; }

  /* ── Question Cards ── */
  .q-card { border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 10px; }
  .q-head-correct   { background: #f0fdf4; border-left: 5px solid #16a34a; padding: 8px 12px; border-radius: 5px 5px 0 0; }
  .q-head-incorrect { background: #fef2f2; border-left: 5px solid #dc2626; padding: 8px 12px; border-radius: 5px 5px 0 0; }
  .q-head-skipped   { background: #f8fafc; border-left: 5px solid #94a3b8; padding: 8px 12px; border-radius: 5px 5px 0 0; }
  .q-head-table { width: 100%; }
  .q-num { font-size: 11px; font-weight: 700; color: #64748b; }
  .q-body { padding: 10px 12px; }
  .q-text { font-size: 12px; color: #0f172a; line-height: 1.6; margin-bottom: 8px; }
  .q-meta { font-size: 9px; color: #94a3b8; margin-bottom: 6px; }

  /* ── Answer Boxes ── */
  .answer-table { width: 100%; border-collapse: separate; border-spacing: 6px; margin-top: 6px; }
  .ans-student { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px; }
  .ans-correct  { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 4px; padding: 8px; }
  .ans-label { font-size: 9px; text-transform: uppercase; color: #94a3b8; font-weight: 700; margin-bottom: 4px; }
  .ans-value { font-size: 11px; color: #0f172a; line-height: 1.5; }

  /* ── Explanation ── */
  .expl-box { background: #fffbeb; border: 1px solid #fde68a; border-radius: 4px; padding: 8px 10px; margin-top: 8px; }
  .expl-label { font-size: 9px; font-weight: 700; color: #92400e; margin-bottom: 3px; }
  .expl-text { font-size: 11px; color: #451a03; line-height: 1.5; }
  .solution-box { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 4px; padding: 8px 10px; margin-top: 6px; }
  .solution-label { font-size: 9px; font-weight: 700; color: #0369a1; margin-bottom: 3px; }
  .feedback-box { background: #f5f3ff; border: 1px solid #c4b5fd; border-radius: 4px; padding: 8px 10px; margin-top: 6px; }
  .feedback-label { font-size: 9px; font-weight: 700; color: #7c3aed; margin-bottom: 3px; }

  .marks-row { font-size: 10px; color: #475569; margin-top: 4px; }
  .neg-red { color: #dc2626; font-weight: 600; }

  /* ── Footer ── */
  .footer-table { width: 100%; background: #f8fafc; border-top: 1px solid #e2e8f0; margin-top: 20px; }
  .footer-table td { padding: 8px 20px; font-size: 9px; color: #94a3b8; }

  .page-break { page-break-before: always; }
  .no-break { page-break-inside: avoid; }
</style>
</head>
<body>

{{-- ══ HEADER ══ --}}
<table class="header-table" cellpadding="0" cellspacing="0">
  <tr>
    <td style="width:60%;">
      <div class="header-title">Quiz Performance Report</div>
      <div class="header-sub">{{ $report['quiz']['title'] }}</div>
    </td>
    <td class="header-right">
      <strong>{{ $report['student']['name'] }}</strong>
      {{ $report['student']['email'] }}<br>
      Generated: {{ \Carbon\Carbon::parse($report['generated_at'])->format('d M Y, h:i A') }}
    </td>
  </tr>
</table>

{{-- ══ RESULT BANNER ══ --}}
@php $passed = $report['score_summary']['is_passed']; @endphp
<div class="{{ $passed ? 'banner-passed' : 'banner-failed' }}" style="margin:0;">
  <table class="banner-table" cellpadding="0" cellspacing="0">
    <tr>
      <td style="vertical-align:middle;">
        <div class="{{ $passed ? 'verdict-passed' : 'verdict-failed' }}">
          {{ $passed ? '✓  PASSED' : '✗  FAILED' }}
        </div>
        <div class="pass-mark">Pass mark: {{ $report['quiz']['pass_percentage'] }}%</div>
      </td>
      <td style="text-align:right; vertical-align:middle;">
        <div class="score-big">{{ $report['score_summary']['final_score'] }} / {{ $report['quiz']['total_marks'] }}</div>
        <div class="score-pct">{{ $report['score_summary']['percentage'] }}%</div>
      </td>
    </tr>
  </table>
</div>

{{-- ══ ATTEMPT INFO ══ --}}
<div class="section">
  <div class="section-title">Attempt Information</div>
  <table class="info-table" cellpadding="4" cellspacing="0">
    <tr>
      <td><div class="info-cell"><div class="info-label">Student</div><div class="info-value">{{ $report['student']['name'] }}</div></div></td>
      <td><div class="info-cell"><div class="info-label">Quiz Type</div><div class="info-value">{{ ucfirst($report['quiz']['type']) }}</div></div></td>
      <td><div class="info-cell"><div class="info-label">Attempt No.</div><div class="info-value">#{{ $report['attempt']['attempt_number'] }}</div></div></td>
      <td><div class="info-cell"><div class="info-label">Status</div><div class="info-value">{{ ucfirst($report['attempt']['status']) }}</div></div></td>
    </tr>
    <tr>
      <td><div class="info-cell"><div class="info-label">Started At</div><div class="info-value" style="font-size:10px;">{{ $report['attempt']['started_at'] }}</div></div></td>
      <td><div class="info-cell"><div class="info-label">Submitted At</div><div class="info-value" style="font-size:10px;">{{ $report['attempt']['submitted_at'] }}</div></div></td>
      <td><div class="info-cell"><div class="info-label">Time Spent</div><div class="info-value">{{ $report['attempt']['time_spent'] }}</div></div></td>
      <td><div class="info-cell"><div class="info-label">Rank</div><div class="info-value">{{ $report['score_summary']['rank'] ? '#'.$report['score_summary']['rank'] : '—' }}</div></div></td>
    </tr>
    @if($report['quiz']['category'])
    <tr>
      <td colspan="2"><div class="info-cell"><div class="info-label">Category</div><div class="info-value">{{ $report['quiz']['category'] }}</div></div></td>
      <td colspan="2"><div class="info-cell"><div class="info-label">Email</div><div class="info-value" style="font-size:10px;">{{ $report['student']['email'] }}</div></div></td>
    </tr>
    @endif
  </table>
</div>

{{-- ══ SCORE STATS ══ --}}
<div class="section" style="margin-top:14px;">
  <div class="section-title">Score Summary</div>
  <table class="stats-table" cellpadding="0" cellspacing="6">
    <tr>
      <td class="stat-cell stat-blue">
        <div class="stat-num">{{ $report['score_summary']['total_questions'] }}</div>
        <div class="stat-lbl">Total</div>
      </td>
      <td class="stat-cell stat-green">
        <div class="stat-num">{{ $report['score_summary']['correct'] }}</div>
        <div class="stat-lbl">Correct</div>
      </td>
      <td class="stat-cell stat-red">
        <div class="stat-num">{{ $report['score_summary']['incorrect'] }}</div>
        <div class="stat-lbl">Incorrect</div>
      </td>
      <td class="stat-cell stat-yellow">
        <div class="stat-num">{{ $report['score_summary']['skipped'] }}</div>
        <div class="stat-lbl">Skipped</div>
      </td>
      <td class="stat-cell stat-purple">
        <div class="stat-num">{{ $report['score_summary']['accuracy'] }}%</div>
        <div class="stat-lbl">Accuracy</div>
      </td>
    </tr>
  </table>

  <table class="info-table" cellpadding="4" cellspacing="0" style="margin-top:6px;">
    <tr>
      <td><div class="info-cell"><div class="info-label">Marks Obtained</div><div class="info-value">{{ $report['score_summary']['marks_obtained'] }}</div></div></td>
      <td><div class="info-cell"><div class="info-label">Negative Deducted</div><div class="info-value neg-red">{{ $report['score_summary']['negative_marks_total'] > 0 ? '-'.$report['score_summary']['negative_marks_total'] : '0' }}</div></div></td>
      <td><div class="info-cell"><div class="info-label">Final Score</div><div class="info-value">{{ $report['score_summary']['final_score'] }}</div></div></td>
      <td><div class="info-cell"><div class="info-label">Percentage</div><div class="info-value">{{ $report['score_summary']['percentage'] }}%</div></div></td>
    </tr>
  </table>
</div>

{{-- ══ BREAKDOWN BY DIFFICULTY ══ --}}
@if(!empty($report['breakdown_by_difficulty']))
<div class="section" style="margin-top:14px;">
  <div class="section-title">Breakdown by Difficulty</div>
  <table class="data-table" cellpadding="0" cellspacing="0">
    <thead>
      <tr>
        <th>Difficulty</th><th>Total</th><th>Correct</th><th>Incorrect</th><th>Skipped</th><th>Marks Scored</th>
      </tr>
    </thead>
    <tbody>
      @foreach($report['breakdown_by_difficulty'] as $diff => $s)
      <tr>
        <td><span class="badge badge-{{ strtolower($diff) }}">{{ ucfirst($diff) }}</span></td>
        <td>{{ $s['total'] }}</td>
        <td style="color:#16a34a; font-weight:700;">{{ $s['correct'] }}</td>
        <td style="color:#dc2626; font-weight:700;">{{ $s['incorrect'] }}</td>
        <td style="color:#94a3b8;">{{ $s['skipped'] }}</td>
        <td style="font-weight:700;">{{ $s['marks'] }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

{{-- ══ BREAKDOWN BY SUBJECT ══ --}}
@if(!empty($report['breakdown_by_subject']))
<div class="section" style="margin-top:14px;">
  <div class="section-title">Breakdown by Subject</div>
  <table class="data-table" cellpadding="0" cellspacing="0">
    <thead>
      <tr>
        <th>Subject</th><th>Total</th><th>Correct</th><th>Incorrect</th><th>Skipped</th><th>Marks</th>
      </tr>
    </thead>
    <tbody>
      @foreach($report['breakdown_by_subject'] as $subject => $s)
      <tr>
        <td style="font-weight:600;">{{ $subject }}</td>
        <td>{{ $s['total'] }}</td>
        <td style="color:#16a34a; font-weight:700;">{{ $s['correct'] }}</td>
        <td style="color:#dc2626; font-weight:700;">{{ $s['incorrect'] }}</td>
        <td style="color:#94a3b8;">{{ $s['skipped'] }}</td>
        <td style="font-weight:700;">{{ $s['marks'] }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

{{-- ══ QUESTION REVIEW ══ --}}
<div class="section page-break" style="margin-top:14px;">
  <div class="section-title">Question-by-Question Review</div>

  @foreach($report['questions'] as $q)
  <div class="q-card no-break">

    {{-- Header --}}
    <div class="q-head-{{ $q['status'] }}">
      <table class="q-head-table" cellpadding="0" cellspacing="0">
        <tr>
          <td style="vertical-align:middle;">
            <span class="q-num">Q{{ $q['number'] }}</span>
            @if($q['subject'])
              &nbsp;<span style="font-size:9px; color:#64748b;">{{ $q['subject'] }}{{ $q['topic'] ? ' › '.$q['topic'] : '' }}</span>
            @endif
          </td>
          <td style="text-align:right; vertical-align:middle;">
            @if($q['difficulty'])
              <span class="badge badge-{{ $q['difficulty'] }}">{{ ucfirst($q['difficulty']) }}</span>&nbsp;
            @endif
            <span class="badge badge-{{ $q['status'] }}">
              @if($q['status'] === 'correct') ✓ Correct
              @elseif($q['status'] === 'incorrect') ✗ Incorrect
              @else — Skipped
              @endif
            </span>&nbsp;
            <span class="badge badge-marks">{{ $q['marks_awarded'] }} / {{ $q['max_marks'] }} marks</span>
          </td>
        </tr>
      </table>
    </div>

    {{-- Body --}}
    <div class="q-body">
      <div class="q-text">{!! nl2br(e($q['question_text'])) !!}</div>

      {{-- Marks meta --}}
      <div class="marks-row">
        Max: {{ $q['max_marks'] }} marks
        @if($q['negative_marks'] > 0) &nbsp;|&nbsp; Negative: {{ $q['negative_marks'] }} @endif
        @if($q['negative_deducted'] > 0) &nbsp;|&nbsp; <span class="neg-red">Deducted: -{{ $q['negative_deducted'] }}</span> @endif
        @if($q['time_spent_sec']) &nbsp;|&nbsp; Time: {{ intdiv($q['time_spent_sec'], 60) }}m {{ $q['time_spent_sec'] % 60 }}s @endif
        @if($q['visit_count'] > 1) &nbsp;|&nbsp; Revisited: {{ $q['visit_count'] }}x @endif
      </div>

      {{-- Answer boxes --}}
      <table class="answer-table" cellpadding="0" cellspacing="6" style="margin-top:8px;">
        <tr>
          <td style="width:50%;">
            <div class="ans-student">
              <div class="ans-label">Your Answer</div>
              <div class="ans-value">
                @if(empty($q['student_answer']))
                  <span style="color:#94a3b8; font-style:italic;">Not answered</span>
                @else
                  {!! nl2br(e($q['student_answer'])) !!}
                @endif
              </div>
            </div>
          </td>
          @if(array_key_exists('correct_answer', $q))
          <td style="width:50%;">
            <div class="ans-correct">
              <div class="ans-label">Correct Answer</div>
              <div class="ans-value">
                @if(empty($q['correct_answer']))
                  <span style="color:#94a3b8;">—</span>
                @else
                  {!! nl2br(e($q['correct_answer'])) !!}
                @endif
              </div>
            </div>
          </td>
          @endif
        </tr>
      </table>

      {{-- Explanation --}}
      @if(array_key_exists('explanation', $q) && !empty($q['explanation']))
      <div class="expl-box">
        <div class="expl-label">Explanation</div>
        <div class="expl-text">{!! nl2br(e($q['explanation'])) !!}</div>
      </div>
      @endif

      @if(array_key_exists('solution_approach', $q) && !empty($q['solution_approach']))
      <div class="solution-box">
        <div class="solution-label">Solution Approach</div>
        <div class="expl-text">{!! nl2br(e($q['solution_approach'])) !!}</div>
      </div>
      @endif

      @if(!empty($q['grader_feedback']))
      <div class="feedback-box">
        <div class="feedback-label">Grader Feedback</div>
        <div class="expl-text">{{ $q['grader_feedback'] }}</div>
      </div>
      @endif

    </div>
  </div>
  @endforeach
</div>

{{-- ══ FOOTER ══ --}}
<table class="footer-table" cellpadding="0" cellspacing="0">
  <tr>
    <td>{{ $report['student']['name'] }} — {{ $report['quiz']['title'] }}</td>
    <td style="text-align:right;">Attempt #{{ $report['attempt']['attempt_number'] }} &nbsp;|&nbsp; {{ $report['attempt']['submitted_at'] }}</td>
  </tr>
</table>

</body>
</html>
