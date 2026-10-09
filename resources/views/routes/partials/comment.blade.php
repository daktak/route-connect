<div class="flex gap-3" x-data="{ editing: false, replyOpen: false }">
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

        <div x-show="!editing" x-html="{{ $comment->content }}"></div>
        <textarea x-show="editing" x-model="editContent" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent" rows="3"></textarea>

        <div class="flex items-center gap-3 mt-2">
            @auth
                @can('update', $comment)
                    <button @click="editing = !editing; editContent = $comment->content" class="text-sm text-primary hover:underline" x-text="editing ? 'Cancel' : 'Edit'"></button>
                    <button @click="updateComment()" x-show="editing" class="text-sm bg-primary text-white px-3 py-1 rounded hover:bg-primary-hover">Save</button>
                @endcan
                @can('delete', $comment)
                    <button @click="deleteComment()" class="text-sm text-red-600 hover:underline">Delete</button>
                @endcan

                <button @click="replyOpen = !replyOpen" class="text-sm text-primary hover:underline">Reply</button>
            @endauth
        </div>

        <!-- Reply Form -->
        <div x-show="replyOpen" x-transition class="mt-3 ml-10 border-l-2 border-gray-200 pl-4">
            @auth
                <form @submit.prevent="submitReply" class="space-y-2">
                    <textarea name="content" rows="2" placeholder="Write a reply..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent" required></textarea>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="replyOpen = false" class="text-sm text-gray-500 hover:underline">Cancel</button>
                        <button type="submit" class="text-sm bg-primary text-white px-3 py-1 rounded hover:bg-primary-hover">Reply</button>
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

        <script>
            function updateComment() {
                fetch('/comments/{{ $comment->id }}', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ content: this.editContent })
                }).then(r => {
                    if (r.ok) location.reload();
                    else alert('Failed to update');
                });
            }

            function deleteComment() {
                if (!confirm('Delete this comment?')) return;
                fetch('/comments/{{ $comment->id }}', {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                }).then(r => r.ok && location.reload());
            }

            function submitReply(e) {
                e.preventDefault();
                const form = e.target;
                const content = form.querySelector('textarea[name="content"]').value;
                fetch('/routes/{{ $comment->route_id }}/comments', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ content, parent_id: {{ $comment->id }} })
                }).then(r => r.ok && location.reload());
            }
        </script>
    </div>
</div>