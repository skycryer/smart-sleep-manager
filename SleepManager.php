<?PHP
/* Smart Sleep Manager - Manual Sleep Button
 * Provides immediate sleep functionality
 */
?>
<?
$plugin = 'smart.sleep.manager';
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
?>
<script>
function sleepNow() {
  $('#sleepbutton').val('Sleeping...');
  if (typeof showNotice == 'function') showNotice('System entering sleep mode');
  for (var i=0,element; element=document.querySelectorAll('input,button,select')[i]; i++) { element.disabled = true; }
  for (var i=0,link; link=document.getElementsByTagName('a')[i]; i++) { link.style.color = "gray"; }
  $.get('/plugins/<?=$plugin?>/include/SleepMode.php',function(){location=location;});
}

function sleepConfirm() {
  swal({
    title: 'Proceed?',
    text: 'This will immediately put the system in sleep mode using Smart Sleep Manager settings.',
    type: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sleep Now',
    cancelButtonText: 'Cancel'
  }, function(){
    sleepNow();
  });
}
</script>

<table class="array_status" style="margin-top:0">
<tr><td></td>
<td><input type="button" id="sleepbutton" value="Sleep Now" onclick="sleepConfirm()"></td>
<td><b>Smart Sleep</b> will immediately put the server in sleep mode using your configured settings.<br>
Make sure your server supports S3 sleep and Wake-on-LAN is properly configured.<br>
<a href="/Settings/SmartSleepSettings" style="text-decoration:underline">Configure Smart Sleep Settings</a>
</td></tr>
</table>