<datalist id="guardian-relations">
    @foreach (config('afghanistan.guardian_relations') as $relation)
        <option value="{{ $relation }}"></option>
    @endforeach
</datalist>
