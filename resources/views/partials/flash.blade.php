@if (session('status'))
    <div class="flash flash-success" role="status">{{ session('status') }}</div>
@endif
@if ($errors->has('visibility'))
    <div class="flash flash-error" role="alert">{{ $errors->first('visibility') }}</div>
@endif
