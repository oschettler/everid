<div id="outliner-nav" class="btn-group">
  <button id="rendermode-status" type="button" title="Render mode" class="btn btn-default">R</button>
  <button type="button" class="btn btn-default">2</button>

  <div class="btn-group">
    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown"><span class="glyphicon glyphicon-chevron-down"></span></button>
    <ul class="dropdown-menu pull-right" role="menu">
			<li><a onclick="opExpand();"><span class="menuKeystroke">⌘,</span>Expand</a></li>
			<li><a onclick="opExpandAllLevels();">Expand All Subs</a></li>
			<li><a onclick="opExpandEverything();">Expand Everything</a></li>
			
			<li class="divider"></li>
			<li><a onclick="opCollapse();"><span class="menuKeystroke">⌘.</span>Collapse</a></li>
			<li><a onclick="opCollapseEverything();">Collapse Everything</a></li>
			
			<li class="divider"></li>
			<li><a onclick="opReorg(up, 1);"><span class="menuKeystroke">⌘U</span>Move Up</a></li>
			<li><a onclick="opReorg(down, 1);"><span class="menuKeystroke">⌘D</span>Move Down</a></li>
			<li><a onclick="opReorg(left, 1);"><span class="menuKeystroke">⌘L</span>Move Left</a></li>
			<li><a onclick="opReorg(right, 1);"><span class="menuKeystroke">⌘R</span>Move Right</a></li>
			
			<li class="divider"></li>
			<li><a onclick="opPromote();"><span class="menuKeystroke">⌘[</span>Promote</a></li>
			<li><a onclick="opDemote();"><span class="menuKeystroke">⌘]</span>Demote</a></li>
			
			<li class="divider"></li>
			<li><a onclick="runSelection();"><span class="menuKeystroke">⌘/</span>Run Selection</a></li>
			<li><a onclick="toggleComment();"><span class="menuKeystroke">⌘\</span>Toggle Comment</a></li>
			
			<li class="divider"></li>
			<li><a onclick="toggleRenderMode();"><span class="menuKeystroke">⌘`</span>Toggle Render Mode</a></li>
    </ul>
  </div>
</div><!-- .btn-group -->
