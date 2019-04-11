!function($, window, document, _undefined)
{
	XenForo.TorrentTabs = function($tabContainer) { this.__construct($tabContainer); };
	XenForo.TorrentTabs.prototype =
	{
		__construct: function($tabContainer)
		{
			this.$tabContainer = $tabContainer;
			this.$panes = $($tabContainer.data('panes'));

			var $tabs = $tabContainer.find('a');
			$tabContainer = this;

			$tabs.each(function(index) {
				$(this).bind('click', $.context($tabContainer, 'click'));
			});
		},

		click: function(e) 
		{
			e.preventDefault();

			var index = 0;
			this.$tabContainer.children().each(function(i) {
				if (this == e.target.parentNode) {
					$(this).addClass('active');
					index = i;
				} else {
					$(this).removeClass('active');
				}
			});

			this.$panes.each(function(i) {
				if (i != index) {
					$(this).removeClass('active');
				}
				else {
					$(this).addClass('active');
				}
			});

			var $container = $(this.$panes.get(index));
			if ($container.find('.torrentList').length)
			{
				return;
			}

			var data = {
				filter: $(e.target).data('filter')
			};

			XenForo.ajax(this.$tabContainer.data('url'), data, function(ajaxData)
			{
				if (XenForo.hasTemplateHtml(ajaxData))
				{
					new XenForo.ExtLoader(ajaxData, function(ajaxData)
					{
						$container.html('');
						$(ajaxData.templateHtml).xfInsert('appendTo', $container, 'xfFadeIn', 0);
					});
				}
				else if (XenForo.hasResponseError(ajaxData))
				{
					return false;
				}
			});
		}
	};

	XenForo.register('.TorrentTabs', 'XenForo.TorrentTabs');
}
(jQuery, this, document);
