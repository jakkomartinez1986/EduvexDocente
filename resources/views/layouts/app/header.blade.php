<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

            <x-app-logo href="{{ route('dashboard') }}" wire:navigate />

            @php
                $nav = app(\App\Services\NavigationService::class);
                $filteredGroups = $nav->filteredGroups();
            @endphp

            <flux:navbar class="-mb-px max-lg:hidden">
                @foreach ($filteredGroups as $groupName => $groupData)
                    <flux:dropdown>
                        <flux:navbar.item
                            icon="{{ $groupData['icon'] }}"
                            icon-trailing="chevron-down"
                            class="cursor-pointer"
                        >
                            {{ $groupName }}
                        </flux:navbar.item>

                        <flux:menu>
                            @foreach ($groupData['links'] as $link)
                                <flux:menu.item
                                    :icon="$link['icon']"
                                    :href="$link['route']"
                                    :current="$link['current']"
                                    wire:navigate
                                >
                                    <div class="flex items-center w-full">
                                        <span class="text-sm">{{ $link['label'] }}</span>

                                        @isset($link['badge'])
                                            <flux:badge
                                                size="xs"
                                                :color="$link['current'] ? 'primary' : $link['color']"
                                                class="ml-auto"
                                            >
                                                {{ $link['badge'] }}
                                            </flux:badge>
                                        @endisset
                                    </div>
                                </flux:menu.item>
                                @if (! $loop->last)
                                    <flux:menu.separator />
                                @endif
                            @endforeach
                        </flux:menu>
                    </flux:dropdown>
                @endforeach
            </flux:navbar>

            <flux:spacer />

            <flux:navbar class="me-1.5 space-x-0.5 rtl:space-x-reverse py-0!">
                <flux:tooltip :content="__('Search')" position="bottom">
                    <flux:navbar.item class="!h-10 [&>div>svg]:size-5" icon="magnifying-glass" href="#" :label="__('Search')" />
                </flux:tooltip>                
            </flux:navbar>

            <x-desktop-user-menu />
        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                @foreach ($filteredGroups as $groupName => $groupData)
                    <flux:sidebar.group :heading="$groupName">
                        @foreach ($groupData['links'] as $link)
                            <flux:sidebar.item
                                icon="{{ $link['icon'] }}"
                                :href="$link['route']"
                                :current="$link['current']"
                                :badge="$link['badge'] ?? null"
                                :badge-color="$link['color'] ?? null"
                                wire:navigate
                            >
                                {{ $link['label'] }}
                            </flux:sidebar.item>
                        @endforeach
                    </flux:sidebar.group>
                @endforeach
            </flux:sidebar.nav>
        </flux:sidebar>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
