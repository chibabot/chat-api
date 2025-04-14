@extends('layouts.app')

@section('title', isset($chat) && isset($chat->is_group) && $chat->is_group ? $chat->name : ($chat->other_user->name ?? 'Чат'))

@section('content')
<div class="container">
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2>
                    <a href="{{ route('chats.index') }}" class="text-decoration-none">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    @if(isset($chat))
                        @if($chat->is_group)
                            {{ $chat->name }}
                        @else
                            {{ $chat->other_user->name ?? 'Чат' }}
                        @endif
                    @else
                        Чат
                    @endif
                </h2>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body" id="message-container" style="height: 400px; overflow-y: auto;">
                    @if(isset($messages) && count($messages) > 0)
                        @foreach($messages as $message)
                            <div class="mb-3 d-flex {{ $message->user_id == auth()->id() ? 'justify-content-end' : 'justify-content-start' }}">
                                <div class="card {{ $message->user_id == auth()->id() ? 'bg-primary text-white' : 'bg-light' }}" style="max-width: 75%;">
                                    <div class="card-body py-2 px-3">
                                        <p class="mb-0">{{ $message->content }}</p>
                                        <small class="d-block text-{{ $message->user_id == auth()->id() ? 'light' : 'muted' }}">
                                            {{ $message->created_at->format('H:i') }}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="text-center py-5">
                            <p>Нет сообщений</p>
                        </div>
                    @endif
                </div>
                
                <div class="card-footer bg-white">
                    <form action="{{ isset($chat) ? route('messages.store', $chat->id) : '#' }}" method="POST">
                        @csrf
                        <div class="input-group">
                            <textarea name="content" class="form-control" placeholder="Введите сообщение..." required></textarea>
                            <button type="submit" class="btn btn-primary">Отправить</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const messageContainer = document.getElementById('message-container');
    if (messageContainer) {
        messageContainer.scrollTop = messageContainer.scrollHeight;
    }
});
</script>
@endpush
@endsection 