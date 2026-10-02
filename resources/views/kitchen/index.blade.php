<x-layouts.app heading="Kitchen display" title="Kitchen display" :show-date-filter="false">
    <div class="page-intro">
        <div><p class="eyebrow">SERVICE IN MOTION</p><h2>Good food. Right on time.</h2><p>Work from left to right. Mark dishes ready when they are ready for collection.</p></div>
        <span class="service-badge"><span class="size-2 rounded-full bg-emerald-500"></span> Live order updates</span>
    </div>
    <x-kitchen-queue :board="true" />
</x-layouts.app>
