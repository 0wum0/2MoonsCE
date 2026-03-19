var v  = new Date();
var Shipyard, Amount, hanger_id, ShipyardInterval;

function ShipyardInit() {
	Shipyard  = data.Queue;
	Amount    = parseInt(Shipyard[0][1], 10);
	hanger_id = data.b_hangar_id_plus;
	$('#timeleft').text(data.pretty_time_b_hangar);
	ShipyardList();
	BuildlistShipyard();
	ShipyardInterval = window.setInterval(BuildlistShipyard, 1000);
}

function BuildlistShipyard() {
	var n = new Date();
	var s = Math.round(Shipyard[0][2] - hanger_id - (n.getTime() - v.getTime()) / 1000);
	if (s <= 0) {
		Amount -= 1;
		$('#val_'+Shipyard[0][3]).text(function(i, old){
			return ' ('+bd_available+NumberGetHumanReadable(parseInt(old.replace(/.* (.*)\)/, '$1').replace(/\./g, ''))+1)+')';
		});
		if (Amount <= 0) {
			Shipyard.shift();
			if (Shipyard.length === 0) {
				$("#bx").html(Ready);
				document.getElementById('auftr').options[0] = new Option(Ready);
				window.clearInterval(ShipyardInterval);
				if (typeof SmAjax !== 'undefined') { SmAjax.refreshPageContent(); } else { document.location.href = document.location.href; }
				return;
			}
			Amount = parseInt(Shipyard[0][1], 10);
			ShipyardList();
		} else {
			document.getElementById('auftr').options[0].innerHTML = Amount + " " + Shipyard[0][0] + " " + bd_operating;
		}
		hanger_id = 0;
		v = new Date();
		s = 0;
	}
	$("#bx").html(Shipyard[0][0] + " " + GetRestTimeFormat(s));
}

function ShipyardList() {
	var sel = document.getElementById('auftr');
	while (sel.options.length > 0) sel.options[sel.options.length - 1] = null;
	for (var iv = 0; iv < Shipyard.length; iv++) {
		var label = (iv === 0 ? Amount : Shipyard[iv][1]) + " " + Shipyard[iv][0] + " " + bd_operating;
		sel.options[iv] = new Option(label, iv);
	}
}