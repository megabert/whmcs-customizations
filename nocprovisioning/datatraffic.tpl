<h1>Data traffic Graphs</h1> 

{if $ports == 0}
No data traffic information available for this server.
{else}

<h2>Current month</h2>

{$graphs1}

{if $graphs2}
<h2>Previous month</h2>

{$graphs2}
{/if}

<p>All times displayed are in the {$timezone} time zone</p>
{/if}