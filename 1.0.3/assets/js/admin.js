/**
 * StripBoard Admin JavaScript
 *
 * @package Stripboard
 */

(function ($) {
    'use strict';

    var StripboardAdmin = {

        init: function () {
            this.cacheElements();
            this.initTabs();
            this.initFeatureToggles();
            this.initSectionToggles();
            this.initSearch();
            this.initFormSubmission();
            this.initNotifications();
            this.initTopSaveButton();
            this.initKeyboardShortcuts();
            this.initBeforeUnload();
            this.refreshAllSectionToggles();
            this.refreshAllParentToggles();
            this.$unsavedIndicator.prop('hidden', true);
            this.$form.removeClass('form-changed');
        },

        cacheElements: function () {
            this.$form = $('#stripboard-form');
            this.$submitButton = $('#stripboard-submit');
            this.$search = $('#wp-feature-search');
            this.$emptyState = $('#wp-feature-search-empty');
            this.$featureItems = $('[data-feature-item]');
            this.$sectionToggles = $('.wp-feature-section-toggle');
            this.$tabs = $('.wp-feature-tab');
            this.$panels = $('.wp-feature-section[role="tabpanel"]');
            this.$topSaveButton = $('#stripboard-submit-top');
            this.$unsavedIndicator = $('#wp-feature-unsaved-indicator');
        },

        strings: function () {
            return (window.stripboard && window.stripboard.strings) ? window.stripboard.strings : {};
        },

        initTabs: function () {
            var self = this;
            var storedTab = window.sessionStorage.getItem('stripboard_active_tab');
            var firstTabKey = this.$tabs.first().data('tab');
            var initialTab = storedTab && this.$tabs.filter('[data-tab="' + storedTab + '"]').length ? storedTab : firstTabKey;

            if (initialTab) {
                this.activateTab(initialTab, false);
            }

            this.$tabs.on('click', function () {
                self.activateTab($(this).data('tab'), true);
            });

            this.$tabs.on('keydown', function (event) {
                var $current = $(this);
                var currentIndex = self.$tabs.index($current);
                var nextIndex = currentIndex;

                if (event.key === 'ArrowRight') {
                    nextIndex = (currentIndex + 1) % self.$tabs.length;
                } else if (event.key === 'ArrowLeft') {
                    nextIndex = (currentIndex - 1 + self.$tabs.length) % self.$tabs.length;
                } else if (event.key === 'Home') {
                    nextIndex = 0;
                } else if (event.key === 'End') {
                    nextIndex = self.$tabs.length - 1;
                } else {
                    return;
                }

                event.preventDefault();
                self.activateTab(self.$tabs.eq(nextIndex).data('tab'), true);
            });
        },

        activateTab: function (tabKey, shouldFocus) {
            var $targetTab = this.$tabs.filter('[data-tab="' + tabKey + '"]');

            if (!$targetTab.length) {
                return;
            }

            this.$tabs
                .removeClass('is-active')
                .attr('aria-selected', 'false')
                .attr('tabindex', '-1');

            $targetTab
                .addClass('is-active')
                .attr('aria-selected', 'true')
                .attr('tabindex', '0');

            this.$panels.removeClass('is-active').attr('aria-hidden', 'true');
            $('#panel-' + tabKey).addClass('is-active').attr('aria-hidden', 'false');

            window.sessionStorage.setItem('stripboard_active_tab', tabKey);

            if (shouldFocus) {
                $targetTab.trigger('focus');
            }
        },

        initFeatureToggles: function () {
            var self = this;

            this.$form.on('change', '.wp-feature-toggle', function () {
                var $toggle = $(this);
                var feature = $toggle.data('feature');
                var isEnabled = $toggle.is(':checked');
                var strings = self.strings();

                if (!isEnabled && self.isCriticalFeature(feature)) {
                    if (!window.confirm(strings.confirmDisable || 'Are you sure you want to disable this feature?')) {
                        $toggle.prop('checked', true);
                        return;
                    }
                }

                self.syncFeatureValue($toggle, isEnabled);
                self.markFormAsChanged();
                self.refreshSectionToggle($toggle.data('category'));

                var $article = $toggle.closest('[data-feature-item]');
                if ($article.length && !$article.is('.wp-feature-item-child')) {
                    var $children = $article.nextUntil(':not(.wp-feature-item-child)');
                    if ($children.length) {
                        $children.find('.wp-feature-toggle').not(':disabled').each(function () {
                            var $childToggle = $(this);
                            if ($childToggle.is(':checked') !== isEnabled) {
                                $childToggle.prop('checked', isEnabled);
                                self.syncFeatureValue($childToggle, isEnabled);
                            }
                        });
                    }
                    self.refreshParentToggle($article);
                } else {
                    self.refreshParentToggle($article);
                }
            });
        },

        initSectionToggles: function () {
            var self = this;

            this.$sectionToggles.on('change', function () {
                var $sectionToggle = $(this);
                var category = $sectionToggle.data('category');
                var shouldEnable = $sectionToggle.is(':checked');
                var $featureToggles = $('.wp-feature-toggle[data-category="' + category + '"]').not(':disabled');

                $featureToggles.each(function () {
                    var $featureToggle = $(this);

                    if ($featureToggle.is(':checked') !== shouldEnable) {
                        $featureToggle.prop('checked', shouldEnable);
                        self.syncFeatureValue($featureToggle, shouldEnable);
                    }
                });

                self.markFormAsChanged();
                self.refreshSectionToggle(category);
            });
        },

        initSearch: function () {
            var self = this;

            this.$search.on('input', function () {
                var query = $.trim($(this).val().toLowerCase());
                var visibleCount = 0;
                var firstVisibleCategory = null;

                self.$featureItems.each(function () {
                    var $item = $(this);
                    var haystack = String($item.data('search') || '');
                    var matches = query === '' || haystack.indexOf(query) !== -1;

                    $item.toggle(matches);

                    if (matches) {
                        visibleCount += 1;
                        if (!firstVisibleCategory) {
                            firstVisibleCategory = $item.data('category');
                        }
                    }
                });

                self.$panels.each(function () {
                    var $section = $(this);
                    var visibleItems = $section.find('[data-feature-item]:visible').length;

                    if (query !== '') {
                        $section.toggleClass('has-search-matches', visibleItems > 0);
                    } else {
                        $section.removeClass('has-search-matches');
                    }
                });

                self.$emptyState.prop('hidden', visibleCount !== 0);

                if (query === '') {
                    var activeTab = self.$tabs.filter('.is-active').data('tab') || self.$tabs.first().data('tab');
                    self.activateTab(activeTab, false);
                    return;
                }

                if (firstVisibleCategory) {
                    self.activateTab(firstVisibleCategory, false);
                }
            });
        },

        syncFeatureValue: function ($toggle, isEnabled) {
            var $item = $toggle.closest('[data-feature-item]');
            var $hidden = $item.find('.wp-feature-hidden-value');
            var $status = $item.find('.wp-feature-toggle-status');
            var strings = this.strings();

            $hidden.val(isEnabled ? '1' : '0');
            $status.text(isEnabled ? (strings.enabled || 'Enabled') : (strings.disabled || 'Disabled'));
        },

        refreshAllSectionToggles: function () {
            var self = this;

            this.$sectionToggles.each(function () {
                self.refreshSectionToggle($(this).data('category'));
            });
        },

        refreshAllParentToggles: function () {
            var self = this;

            $('.wp-feature-item-parent').each(function () {
                self.refreshParentToggle($(this));
            });
        },

        refreshSectionToggle: function (category) {
            var $sectionToggle = this.$sectionToggles.filter('[data-category="' + category + '"]');
            var $availableToggles = $('.wp-feature-toggle[data-category="' + category + '"]').not(':disabled');
            var allEnabled = $availableToggles.length > 0 && $availableToggles.filter(':checked').length === $availableToggles.length;

            $sectionToggle.prop('checked', allEnabled);
        },

        refreshParentToggle: function ($article) {
            if (!$article || !$article.length) {
                return;
            }

            var $parent;
            var $children;

            if ($article.is('.wp-feature-item-child')) {
                $parent = $article.prevAll(':not(.wp-feature-item-child)').first();
                $children = $parent.nextUntil(':not(.wp-feature-item-child)');
            } else {
                $parent = $article;
                $children = $article.nextUntil(':not(.wp-feature-item-child)');
            }

            if (!$parent.length || !$children.length) {
                return;
            }

            var anyChildEnabled = $children.find('.wp-feature-toggle').not(':disabled').is(':checked');
            var $parentToggle = $parent.find('.wp-feature-toggle');

            if ($parentToggle.is(':checked') !== anyChildEnabled) {
                $parentToggle.prop('checked', anyChildEnabled);
                var parentFeature = $parentToggle.data('feature');
                if (parentFeature) {
                    this.syncFeatureValue($parentToggle, anyChildEnabled);
                }
            }
        },

        isCriticalFeature: function (feature) {
            var criticalFeatures = ['posts', 'pages', 'comments', 'rest_api', 'search', 'wc_checkout_blocks', 'wc_cart_fragments', 'design_system', 'site_editor'];
            return criticalFeatures.indexOf(feature) !== -1;
        },

        markFormAsChanged: function () {
            var strings = this.strings();
            this.$form.addClass('form-changed');
            this.$submitButton.val(strings.saveChanges || 'Save Changes');
            this.$submitButton.addClass('button-primary-changed');
            this.$topSaveButton.text(strings.saveChanges || 'Save Changes');
            this.$unsavedIndicator.prop('hidden', false);
        },

        initFormSubmission: function () {
            var self = this;

            this.$form.on('submit', function () {
                var strings = self.strings();

                self.$submitButton
                    .val(strings.savingChanges || 'Saving changes...')
                    .prop('disabled', true);

                self.$topSaveButton
                    .text(strings.savingChanges || 'Saving changes...')
                    .prop('disabled', true);

                self.$form.addClass('stripboard-loading');
                sessionStorage.setItem('stripboard_show_success', 'true');
                $(window).off('beforeunload');
            });

            if (sessionStorage.getItem('stripboard_show_success')) {
                sessionStorage.removeItem('stripboard_show_success');
                this.showNotification(this.strings().changesSaved || 'Changes saved successfully!', 'success');
            }
        },

        initNotifications: function () {
            $(document).on('click', '.stripboard-notification .notice-dismiss', function (event) {
                event.preventDefault();
                $(this).closest('.stripboard-notification').fadeOut(function () {
                    $(this).remove();
                });
            });
        },

        initTopSaveButton: function () {
            var self = this;

            this.$topSaveButton.on('click', function () {
                self.submitSettingsForm();
            });
        },

        submitSettingsForm: function () {
            var formElement = this.$form.get(0);

            if (!formElement) {
                return;
            }

            if (typeof formElement.requestSubmit === 'function') {
                formElement.requestSubmit();
                return;
            }

            this.$form.trigger('submit');
        },

        initKeyboardShortcuts: function () {
            var self = this;

            $(document).on('keydown', function (event) {
                if ((event.ctrlKey || event.metaKey) && event.which === 83 && self.$form.length) {
                    event.preventDefault();
                    self.submitSettingsForm();
                }

                if (event.which === 27) {
                    $('.stripboard-notification').fadeOut(function () {
                        $(this).remove();
                    });
                }
            });
        },

        initBeforeUnload: function () {
            var self = this;

            $(window).on('beforeunload', function () {
                if (self.$form.hasClass('form-changed')) {
                    return self.strings().unsavedChanges || 'You have unsaved changes. Are you sure you want to leave?';
                }

                return undefined;
            });
        },

        showNotification: function (message, type) {
            var strings = this.strings();
            var notificationType = type === 'error' ? 'error' : 'success';
            var $notification = $('<div>', {
                'class': 'stripboard-notification ' + notificationType
            });
            var $message = $('<p>').text(String(message || ''));
            var $dismissButton = $('<button>', {
                type: 'button',
                'class': 'notice-dismiss'
            });

            $dismissButton.append(
                $('<span>', {
                    'class': 'screen-reader-text',
                    text: strings.dismissNotice || 'Dismiss this notice.'
                })
            );

            $notification.append($message, $dismissButton);

            $('body').append($notification);

            window.setTimeout(function () {
                $notification.fadeOut(function () {
                    $(this).remove();
                });
            }, 5000);

            if (type !== 'error') {
                this.$unsavedIndicator.prop('hidden', true);
                this.$topSaveButton
                    .text(strings.saveChanges || 'Save Changes')
                    .prop('disabled', false);
            }
        }
    };

    $(document).ready(function () {
        StripboardAdmin.init();
        window.StripboardAdmin = StripboardAdmin;
    });

})(jQuery);
