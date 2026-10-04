{{-- "Tell us something": a concern, or (if allowed) a care plan change request. --}}
<form class="form" method="POST" action="{{ $action }}">
  @csrf
  <div class="field">
    <label for="category">What is it about?</label>
    <select id="category" name="category" required>
      @if ($canRequestChange ?? false)
        <option value="plan_change" @selected(old('category') === 'plan_change')>A change to the care plan</option>
      @endif
      @foreach (\App\Models\Concern::CATEGORIES as $value => $label)
        <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="detail">Tell us</label>
    <textarea id="detail" name="detail" rows="4" maxlength="2000" required>{{ old('detail') }}</textarea>
    <p class="hint">This goes to the office, not to the caregiver. In an emergency, call 999 first.</p>
  </div>
  <button class="btn btn-primary" type="submit">Send to the office</button>
</form>
