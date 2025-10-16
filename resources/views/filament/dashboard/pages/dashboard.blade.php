<x-filament-panels::page wire:poll.10s="pollForUpdates">
    @if($shouldPoll)
        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex items-center">
                <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600 mr-3"></div>
                <div>
                    <h3 class="text-sm font-medium text-blue-800">Processing Resume</h3>
                    <p class="text-sm text-blue-600">Your resume is being analyzed and parsed. This may take a few
                        moments...</p>
                </div>
            </div>
        </div>
    @endif
    {{-- Page content --}}
    {{ $this->form }}
</x-filament-panels::page>