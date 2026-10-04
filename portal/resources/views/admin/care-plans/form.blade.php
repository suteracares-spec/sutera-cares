@extends('layouts.app')
@section('title', 'Care plan v' . $plan->version . ' · ' . $plan->patient->name)

@php
  use App\Models\CarePlanTask;

  // Old input wins after a failed save, so nothing typed is lost. Then the
  // saved tasks, then spare rows to type into.
  $rows = old('tasks', $plan->tasks->map->only(['category', 'description', 'frequency', 'time_of_day'])->all());
  $rows = array_values($rows);
  $spare = max(3, 8 - count($rows));
  for ($i = 0; $i < $spare; $i++) {
      $rows[] = ['category' => '', 'description' => '', 'frequency' => 'every_visit', 'time_of_day' => 'any'];
  }
@endphp

@section('content')
  <div class="pagehead">
    <div>
      <h1>Care plan &middot; version {{ $plan->version }}</h1>
      <p><a href="{{ route('admin.patients.show', $plan->patient) }}">{{ $plan->patient->name }}</a>
         <span class="code">{{ $plan->patient->code }}</span> &middot; <span class="pill new">Draft</span></p>
    </div>
  </div>

  @if ($errors->any())
    <div class="errors">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form class="form form-wide" method="POST" action="{{ route('admin.care-plans.update', $plan) }}">
    @csrf
    @method('PUT')

    <fieldset>
      <legend>The plan</legend>
      <div class="grid2">
        <div class="field">
          <label for="effective_from">Takes effect from *</label>
          <input id="effective_from" name="effective_from" type="date" required
                 value="{{ old('effective_from', $plan->effective_from?->format('Y-m-d')) }}">
          <p class="hint">The version before this one stops the day before.</p>
        </div>
      </div>
      <div class="field">
        <label for="notes">How this person likes things done</label>
        <textarea id="notes" name="notes" rows="4"
                  placeholder="e.g. Prefers a female caregiver. Hard of hearing on the left. Likes tea before her bath, not after.">{{ old('notes', $plan->notes) }}</textarea>
        <p class="hint">What a new caregiver should read before their first visit. Encrypted at rest.</p>
      </div>
    </fieldset>

    <fieldset>
      <legend>Tasks</legend>
      <p class="hint" style="margin:-6px 0 14px">
        What the caregiver does, in plain words they can tick off on a phone. Empty rows are ignored.
        Clinical tasks are refused: no injections, wound dressing, IV lines, catheters or dose changes.
      </p>

      <div class="scroll">
        <table class="tasks" id="tasks">
          <thead>
            <tr><th style="width:170px">Category</th><th>Task</th><th style="width:140px">How often</th><th style="width:130px">When</th></tr>
          </thead>
          <tbody>
            @foreach ($rows as $i => $row)
              <tr>
                <td>
                  <select name="tasks[{{ $i }}][category]" aria-label="Category">
                    <option value="">-</option>
                    @foreach (CarePlanTask::CATEGORIES as $value => $label)
                      <option value="{{ $value }}" @selected(($row['category'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                  </select>
                </td>
                <td>
                  <input name="tasks[{{ $i }}][description]" value="{{ $row['description'] ?? '' }}" maxlength="400"
                         aria-label="Task" @class(['invalid' => $errors->has("tasks.$i.description") || $errors->has("tasks.$i.category")])>
                </td>
                <td>
                  <select name="tasks[{{ $i }}][frequency]" aria-label="How often">
                    @foreach (CarePlanTask::FREQUENCIES as $value => $label)
                      <option value="{{ $value }}" @selected(($row['frequency'] ?? 'every_visit') === $value)>{{ $label }}</option>
                    @endforeach
                  </select>
                </td>
                <td>
                  <select name="tasks[{{ $i }}][time_of_day]" aria-label="When">
                    @foreach (CarePlanTask::TIMES as $value => $label)
                      <option value="{{ $value }}" @selected(($row['time_of_day'] ?? 'any') === $value)>{{ $label }}</option>
                    @endforeach
                  </select>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <button class="btn btn-quiet" type="button" id="add-row" style="margin-top:12px" hidden>Add another row</button>
    </fieldset>

    <div class="actions">
      <button class="btn btn-primary" type="submit" name="done" value="1">Save and review</button>
      <button class="btn btn-quiet" type="submit">Save and keep editing</button>
      <a class="btn btn-quiet" href="{{ route('admin.care-plans.show', $plan) }}">Cancel</a>
    </div>
  </form>

  <script>
    // Extra rows without a page reload. Without JavaScript the form still
    // works: save, and the page comes back with fresh spare rows.
    (function () {
      var button = document.getElementById('add-row');
      var body = document.querySelector('#tasks tbody');
      button.hidden = false;
      button.addEventListener('click', function () {
        var last = body.rows[body.rows.length - 1];
        var row = last.cloneNode(true);
        var index = body.rows.length;
        row.querySelectorAll('[name]').forEach(function (el) {
          el.name = el.name.replace(/tasks\[\d+\]/, 'tasks[' + index + ']');
          if (el.tagName === 'INPUT') el.value = '';
          el.classList.remove('invalid');
        });
        row.querySelector('select').value = '';
        body.appendChild(row);
        row.querySelector('select').focus();
      });
    })();
  </script>
@endsection
