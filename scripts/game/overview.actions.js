$(function() {
	if($('#tabs').length) {
		$('#tabs').tabs();
	}
});

function checkrename()
{
	var name = $.trim($('#name').val());
	if(name === '') {
		return false;
	}
	$.ajax({
		url:  'game.php?page=overview&mode=rename&ajax=1',
		type: 'POST',
		data: { name: name },
		dataType: 'json',
		success: function(response) {
			if (response && response.message) alert(response.message);
			if (response && !response.error) {
				parent.location.reload();
			}
		},
		error: function(xhr) {
			alert('Fehler beim Speichern. Antwort: ' + xhr.responseText.substring(0, 200));
		}
	});
}

function checkcancel()
{
	var password = $('#password').val();
	if(password == '') {
		return false;
	} else {
		$.post('game.php?page=overview', {'mode' : 'delete', 'password': password}, function(response) {
			alert(response.message);
			if(response.ok){
				parent.location.reload();
			}
		}, "json");
	}
}