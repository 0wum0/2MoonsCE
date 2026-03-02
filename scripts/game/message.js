Message = {
	MessID: 100,
	Page: 1,
	SearchTerm: '',
	HasActiveSearch: false,

	getMessages: function(MessID, page) {
		if (typeof page === 'undefined') {
			page = 1;
		}

		Message.MessID = parseInt(MessID, 10) || 100;
		Message.Page = parseInt(page, 10) || 1;

		var $loading = $('#mcc-loading');
		$loading.css('display', 'flex');

		$.get('game.php?page=messages&mode=view&messcat=' + Message.MessID + '&site=' + Message.Page + '&ajax=1', function(data) {
			$('#messages-view').html(data);
			$loading.hide();

			if (Message.HasActiveSearch) {
				Message.applySearchFilter();
			}

			if (window.history && window.history.replaceState) {
				window.history.replaceState(null, '', 'game.php?page=messages&category=' + Message.MessID + '&side=' + Message.Page);
			}
		}).fail(function() {
			$('#messages-view').html('<div style="padding:40px;text-align:center;color:#4a5a72;font-size:13px;">Nachrichten konnten nicht geladen werden.</div>');
			$loading.hide();
		});
	},

	deleteMessage: function(messageId, messCat, page) {
		var currentCategory = parseInt(messCat, 10) || Message.MessID;
		var currentPage = parseInt(page, 10) || Message.Page;

		if (!window.confirm('Nachricht wirklich löschen?')) {
			return false;
		}

		var payload = { 'messageID[]': String(messageId) };

		if (typeof SmAjax !== 'undefined') {
			SmAjax.post('game.php?page=messages&mode=delete', payload, { noRefresh: true })
				.done(function() {
					Message.getMessages(currentCategory, currentPage);
				})
				.fail(function(err) {
					SmAjax.toastError(err || 'Nachricht konnte nicht gelöscht werden.');
				});
		} else {
			var $form = $('<form>', { method: 'post', action: 'game.php?page=messages' });
			$form.append($('<input>', { type: 'hidden', name: 'mode', value: 'action' }));
			$form.append($('<input>', { type: 'hidden', name: 'messcat', value: currentCategory }));
			$form.append($('<input>', { type: 'hidden', name: 'page', value: currentPage }));
			$form.append($('<input>', { type: 'hidden', name: 'actionTop', value: 'deletemarked' }));
			$form.append($('<input>', { type: 'hidden', name: 'submitTop', value: '1' }));
			$form.append($('<input>', { type: 'hidden', name: 'messageID[' + messageId + ']', value: String(messageId) }));
			$('body').append($form);
			$form.trigger('submit');
		}
		return false;
	},

	forwardMessage: function(subject) {
		var cleanSubject = String(subject || '').trim();
		if (cleanSubject.length === 0) { cleanSubject = 'Nachricht'; }
		return Dialog.open('game.php?page=messages&mode=write&subject=' + encodeURIComponent('Fwd: ' + cleanSubject), 700, 520);
	},

	openCompose: function() {
		return Dialog.open('game.php?page=messages&mode=write', 700, 520);
	},

	applySearchFilter: function() {
		var query = Message.SearchTerm;
		var $items = $('#messages-view .msg-item');

		if (!query.length || !Message.HasActiveSearch) {
			$items.show();
			return;
		}

		$items.each(function() {
			var text = $(this).text().toLowerCase();
			$(this).toggle(text.indexOf(query) !== -1);
		});
	},

	init: function() {
		var initialCategory = parseInt($('#message-category-select').val(), 10);
		var initialPage = parseInt($('#message-current-page').val(), 10);

		if (!initialCategory || isNaN(initialCategory)) { initialCategory = 100; }
		if (!initialPage || isNaN(initialPage)) { initialPage = 1; }

		$('#message-search').val('');
		Message.SearchTerm = '';
		Message.HasActiveSearch = false;

		$('#message-search').on('input', function() {
			Message.SearchTerm = String($(this).val() || '').toLowerCase().trim();
			Message.HasActiveSearch = Message.SearchTerm.length > 0;
			Message.applySearchFilter();
		});

		Message.getMessages(initialCategory, initialPage);
	}
};

$(function() {
	Message.init();
});