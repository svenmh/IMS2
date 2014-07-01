<!DOCTYPE html><?php /* -*- mode: html -*- */
require_once('ims/ims.php');
$ims=new IMS\config('ims.json');
?>

<html lang="en"><head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta charset="utf-8">
<title><?php print $ims->title(); ?></title>
<?php print $ims->html_head(); ?>
<link href="ims.css" rel="stylesheet"/>
<script src="ims.js"></script>
<script src="Publication.js"></script>
<script src="Interaction.js"></script>
<script src="Interaction_source.js"></script>
<script src="Interaction_type.js"></script>
</head><body>

<h1 id="title"><?php print $ims->title(); ?> <small>Ver:<?php print $ims->version(); ?></small></h1>

<div class="container">
<ul class="nav nav-tabs">
  <li class="active"><a href="#interaction_tab" data-toggle="tab">Interactions</a></li>
  <!-- li><a href="#conversion" data-toggle="tab">ID Conversion</a></li -->
  <li><a href="#log_tab" data-toggle="tab">Log (<span class="log-count">0</span>)</a></li>
</ul>
</div>

<div class="tab-content container">

  <div class="tab-pane active" id="interaction_tab">
    <h1>Publication</h1>
    <input type="hidden" id="pubmed" style="width:100%">
    <blockquote class="dropdown" id="publication"></blockquote>
    <h1>Interactions <span class="interaction-count"></span></h1>
    <table id="interactions" class="table table-hover"><thead/><tbody/></table>
  </div>
  <!-- div class="tab-pane" id="conversion">Convert</div -->
  <div class="tab-pane" id="log_tab">
    <h1>Messages</h1>
    <button onclick="$('#log').html('');$('.log-count').html(0)">Clear</button>
    <div id="log"></div>
  </div>
</div>





</body></html>
