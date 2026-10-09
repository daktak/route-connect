<div class="flex gap-3"
     x-data="commentItem"
     data-comment-id="{{ $comment->id }}"
     data-route-id="{{ $comment->route_id }}"
     data-content="{{ $comment->content }}">
    <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-medium flex-shrink-0">
        {{ strtoupper($comment->user->name[0]) }}
    </div>
    <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 mb-1">
            <span class="font-medium text-gray-900">{{ $comment->user->name }}</span>
            <span class="text-sm text-gray-500">{{ $comment->created_at->diffForHumans() }}</span>
            @if($comment->is_edited)
                <span class="text-xs text-gray-400">(edited)</span>
            @endif
        </div>

        <div x-show="!editing" class="text-gray-700 whitespace-pre-line">{{ $comment->content }}</div>
        <textarea x-show="editing" x-model="editContent" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent" rows="3" x-cloak></textarea>

        <div class="flex items-center gap-3 mt-2">
            @auth
                @can('update', $comment)
                    <button type="button" @click="toggleEdit()" class="text-sm text-primary hover:underline" x-text="editing ? 'Cancel' : 'Edit'"></button>
                    <button type="button" @click="updateComment()" x-show="editing" x-cloak class="text-sm bg-primary text-white px-3 py-1 rounded hover:bg-primary-hover">Save</button>
                @endcan
                @can('delete', $comment)
                    <button type="button" @click="deleteComment()" class="text-sm text-red-600 hover:underline">Delete</button>
                @endcan

                <button type="button" @click="replyOpen = !replyOpen" class="text-sm text-primary hover:underline">Reply</button>
            @endauth
        </div>

        <!-- Reply Form -->
        <div x-show="replyOpen" x-cloak x-transition class="mt-3 ml-10 border-l-2 border-gray-200 pl-4">
            @auth
                <form @submit.prevent="submitReply()" class="space-y-2">
                    <textarea name="content" x-model="replyContent" rows="2" placeholder="Write a reply..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent" required></textarea>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="replyOpen = false" class="text-sm text-gray-500 hover:underline">Cancel</button>
                        <button type="submit" :disabled="saving" class="text-sm bg-primary text-white px-3 py-1 rounded hover:bg-primary-hover disabled:opacity-50">Reply</button>
                    </div>
                </form>
            @else
                <p class="text-sm text-gray-500"><a href="{{ route('login') }}" class="text-primary hover:underline">Log in</a> to reply.</p>
            @endauth
        </div>

        <!-- Replies -->
        @if($comment->replies->count())
            <div class="mt-4 ml-10 border-l-2 border-gray-200 pl-4 space-y-3">
                @foreach($comment->replies as $reply)
                    @include('routes.partials.comment', ['comment' => $reply])
                @endforeach
            </div>
        @endif
    </div>
</div>
