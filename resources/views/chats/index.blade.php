@extends('layouts.app')

@section('title', 'Чаты')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12 mb-4">
            <h1>Ваши чаты</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    @if(isset($chats) && count($chats) > 0)
                        <div class="list-group">
                            @foreach($chats as $chat)
                                <a href="{{ route('chats.show', $chat->id) }}" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1">
                                            @if($chat->is_group)
                                                {{ $chat->name }}
                                            @else
                                                {{ $chat->other_user->name ?? 'Пользователь' }}
                                            @endif
                                        </h5>
                                        <small>{{ $chat->last_message_at ? $chat->last_message_at->diffForHumans() : 'Нет сообщений' }}</small>
                                    </div>
                                    <p class="mb-1">{{ $chat->last_message ? Str::limit($chat->last_message->content, 50) : 'Нет сообщений' }}</p>
                                    @if($chat->unread_count)
                                        <span class="badge bg-primary rounded-pill">{{ $chat->unread_count }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-5">
                            <h4>У вас пока нет чатов</h4>
                            <p>Начните общение с другими пользователями</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 