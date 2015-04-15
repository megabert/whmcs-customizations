<h1>Data traffic Graphs</h1> 

{if $ports == 0}
No data traffic information available for this server.
{else}

<h2>Current month</h2>

{section name=port start=0 loop=$ports}
<p>
<img width="497" height="249" alt="Current month" src="{$smarty.server.PHP_SELF}?action=productdetails&amp;id={$serviceid|escape}&amp;subid={$smarty.section.port.index}&amp;start={$startgraph1}&amp;end={$endgraph1}&amp;modop=custom&amp;a=graph&amp;nps_nonce={$nonce}" />
</p>
{/section}

{if $startgraph2}
<h2>Previous month</h2>

{section name=port start=0 loop=$ports}
<p>
<img width="497" height="249" alt="Previous month" src="{$smarty.server.PHP_SELF}?action=productdetails&amp;id={$serviceid|escape}&amp;subid={$smarty.section.port.index}&amp;start={$startgraph2}&amp;end={$endgraph2}&amp;modop=custom&amp;a=graph&amp;nps_nonce={$nonce}" />
</p>
{/section}
{/if}

{/if}