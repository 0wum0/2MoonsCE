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

		$('#loading').show();

		$.get('game.php?page=messages&mode=view&messcat=' + Message.MessID + '&site=' + Message.Page + '&ajax=1', function(data) {
			$('#messages-view').html(data);
			$('#loading').hide();
			$('#message-category-select').val(String(Message.MessID));
			if (Message.HasActiveSearch) {
				Message.applySearchFilter();
			}

			if (window.history && window.history.replaceState) {
				window.history.replaceState(null, '', 'game.php?page=messages&category=' + Message.MessID + '&side=' + Message.Page);
			}
		}).fail(function() {
			$('#messages-view').html('<div class="glass-panel msg-error">Nachrichten konnten nicht geladen werden.</div>');
			$('#loading').hide();
		});
	},

	deleteMessage: function(messageId, messCat, page) {
		var currentCategory = parseInt(messCat, 10) || Message.MessID;
		var currentPage = parseInt(page, 10) || Message.Page;

		if (!window.confirm('Nachricht wirklich loeschen?')) {
			return false;
		}

		var $form = $('<form>', {
			method: 'post',
			action: 'game.php?page=messages'
		});

		$form.append($('<input>', { type: 'hidden', name: 'mode', value: 'action' }));
		$form.append($('<input>', { type: 'hidden', name: 'messcat', value: currentCategory }));
		$form.append($('<input>', { type: 'hidden', name: 'page', value: currentPage }));
		$form.append($('<input>', { type: 'hidden', name: 'actionTop', value: 'deletemarked' }));
		$form.append($('<input>', { type: 'hidden', name: 'submitTop', value: '1' }));
		$form.append($('<input>', { type: 'hidden', name: 'messageID[' + messageId + ']', value: String(messageId) }));

		$('body').append($form);
		$form.trigger('submit');
		return false;
	},

	forwardMessage: function(subject) {
		var cleanSubject = String(subject || '').trim();
		if (cleanSubject.length === 0) {
			cleanSubject = 'Nachricht';
		}
		return Dialog.open('game.php?page=messages&mode=write&subject=' + encodeURIComponent('Fwd: ' + cleanSubject), 700, 430);
	},

	openCompose: function() {
		return Dialog.open('game.php?page=messages&mode=write', 700, 430);
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

		if (!initialCategory || isNaN(initialCategory)) {
			initialCategory = 100;
		}
		if (!initialPage || isNaN(initialPage)) {
			initialPage = 1;
		}

		// Avoid browser-restored stale query hiding all rows after reload.
		$('#message-search').val('');
		Message.SearchTerm = '';
		Message.HasActiveSearch = false;

		$('#message-category-select').on('change', function() {
			Message.getMessages($(this).val(), 1);
		});

		$('#message-refresh').on('click', function(event) {
			event.preventDefault();
			Message.getMessages(Message.MessID, Message.Page);
		});

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