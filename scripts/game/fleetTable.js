$(function() {
	window.setInterval(function() {
		$('.fleets, .timer-cell').each(function() {
			var initial = $(this).data('fleet-time');
			if(typeof initial === 'undefined') {
				initial = $(this).data('time');
			}
			var s = Number(initial || 0) - (serverTime.getTime() - startTime) / 1000;
			if(s <= 0) {
				$(this).text('-');
			} else {
				$(this).text(GetRestTimeFormat(s));
			}
		});
	}, 1000);
});
