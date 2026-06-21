//////////////////////////////////////////////////////////////////////////////80
// Atheos MiniMap
//////////////////////////////////////////////////////////////////////////////80
// Copyright (c) 2020 Liam Siira (liam@siira.io), distributed as-is and without
// warranty under the MIT License. See [root]/license.md for more.
// This information must remain intact.
//////////////////////////////////////////////////////////////////////////////80
// Copyright (c) 2013 Codiad & Anr3as
// Source: https://github.com/Andr3as/Codiad-Minimap
//////////////////////////////////////////////////////////////////////////////80


(function() {
	'use strict';

	const self = {

		path: atheos.path + 'plugins/Minimap/',
		worker: null,
		template: `<div id="minimap"><div class="overlay"></div><pre><code></code></pre></div>`,

		cache: {},

		overlay: null,
		code: null,
		pre: null,

		active: null,
		activePath: null,

		init: function() {
			oX('#EDITOR').prepend(self.template);

			self.overlay = oX('#minimap .overlay');
			self.code = oX('#minimap code');
			self.pre = oX('#minimap pre');

			//Get worker
			self.worker = new Worker(self.path + 'worker.js');
			self.worker.addEventListener('message', self.getWorkerResult);

			self.throttleChange = throttle(self.createMap, 5000, self);
			self.throttleScroll = throttle(self.moveOverlay, 100, self);

			//document on change listener
			carbon.subscribe("active.focus", function(path) {
				self.active = atheos.inFocusEditor;
				if (!self.active) return;

				if (path !== self.activePath) {
					self.activePath = path;
					self.createMap(path);
					var session = self.active.getSession();
					session.on('change', self.throttleChange);
					session.on('changeScrollTop', self.throttleScroll);
				}
			});

			//Reset Canvas
			carbon.subscribe("active.close, active.closeAll", function(path) {
				self.resetMap();
			});

			//Click listener
			oX('#minimap pre', true).on('click', function(e) {
				if (!self.active) return;
				var y = e.pageY;
				var offset = self.pre.offset().top;
				var line = Math.floor((y - offset) / (self.height / self.length));
				atheos.editor.gotoLine(line);
			});
		},

		createMap: function(path) {
			if (path in self.cache) {
				self.render(self.cache[path]);
				return;
			}
			var session = atheos.inFocusSession;
			if (!session) return;
			var code = session.getValue();
			self.worker.postMessage({
				code
			});
		},

		getWorkerResult: function(e) {
			self.cache[self.activePath] = e.data.code;
			self.render(e.data.code);
		},

		render: function(html) {
			self.code.html(html);

			self.height = self.pre.height();
			self.length = self.active.getSession().getLength();

			self.moveOverlay(true);
		},

		lines: 0,
		size: 0,
		length: 0,
		height: 0,

		moveOverlay: function(build) {
			var first = self.active.renderer.getFirstFullyVisibleRow() - 1;
			if (build) {
				var last = self.active.renderer.getLastFullyVisibleRow() + 1;
				self.lines = last - first;
				self.size = self.height / self.length * self.lines;
				self.overlay.css('height', self.size + "px");
			}
			var offset = self.height / self.length * first;
			self.overlay.css('margin-top', offset + "px");
		},

		resetMap: function() {
			self.code.empty();
			self.overlay.css('height', 0);

			self.active = null;
			self.length = 0;
			self.height = 0;
		}
	};

	carbon.subscribe('system.loadExtra', () => self.init());
	atheos.MiniMap = self;

})();