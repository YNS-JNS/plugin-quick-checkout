jQuery(document).ready(function ($) {
  // Admin Tabs Functionality
  var $tabWrapper = $('.qcp-nav-tab-wrapper');
  var $tabLinks = $tabWrapper.find('.nav-tab');
  var $tabContents = $('.qcp-tab-content');

  // Function to show a specific tab based on its ID
  function showTab(tabId) {
    // Remove active class from all links and hide all content
    $tabLinks.removeClass('nav-tab-active');
    $tabContents.hide();

    // Find the link and content corresponding to the tabId
    var $activeLink = $tabWrapper.find('a[href*="tab=' + tabId.replace('qcp-tab-', '') + '"]'); // Find link by section ID in href
    var $activeContent = $('#' + tabId);

    // Add active class to the link and show the content
    if ($activeLink.length) {
      $activeLink.addClass('nav-tab-active');
    }
    if ($activeContent.length) {
      $activeContent.show();
    } else {
      // Fallback: if content not found, show the first one
      $tabContents.first().show();
      $tabLinks.first().addClass('nav-tab-active');
    }
  }

  // Check URL for an existing tab hash on page load
  var currentUrl = window.location.href;
  var currentHash = window.location.hash; // e.g., #qcp-tab-order
  var urlParams = new URLSearchParams(window.location.search);
  var currentQueryTab = urlParams.get('tab'); // e.g., qcp_order_section
  var defaultTabId = $tabContents.first().attr('id'); // ID of the first tab content

  var initialTabId = null;

  if (currentQueryTab) {
    // Find the tab content ID matching the section ID in the query param
    var $matchingLink = $tabWrapper.find('a[href*="tab=' + currentQueryTab + '"]');
    if ($matchingLink.length) {
      var href = $matchingLink.attr('href');
      // Extract the target ID from the href (e.g., #qcp-tab-order)
      try {
        var url = new URL(href, window.location.origin); // Need base for URL constructor
        var targetSection = new URLSearchParams(url.search).get('tab');
        // Find the section in the PHP sections array (or infer ID)
        // This part is tricky without passing PHP sections array to JS.
        // Let's try matching the content div ID directly if possible.
        // We know the tab content ID format is 'qcp-tab-{section_name_part}'
        var potentialTabId =
          'qcp-tab-' + currentQueryTab.replace('qcp_', '').replace('_section', '');
        if ($('#' + potentialTabId).length) {
          initialTabId = potentialTabId;
        }
      } catch (e) {
        console.error('Error parsing tab URL', e);
      }
    }
  }
  // If no valid query tab, use hash if present
  else if (currentHash && $(currentHash).length && $(currentHash).hasClass('qcp-tab-content')) {
    initialTabId = currentHash.substring(1); // Remove #
  }

  // Show the initial tab (found or default)
  showTab(initialTabId || defaultTabId);

  // --- Handle Tab Clicks ---
  $tabLinks.on('click', function (e) {
    e.preventDefault(); // Prevent page jump

    var $clickedLink = $(this);
    var targetHref = $clickedLink.attr('href');
    var newTabId = '';

    // Extract target content ID from the link's href
    try {
      var url = new URL(targetHref);
      var targetSection = url.searchParams.get('tab'); // Get the section ID (e.g., qcp_fields_section)
      // Infer the target content div ID
      newTabId = 'qcp-tab-' + targetSection.replace('qcp_', '').replace('_section', '');
    } catch (e) {
      console.error('QCP Admin: Could not parse tab link href: ', targetHref);
      // Fallback if URL parsing fails (e.g. href is just #hash)
      var hash = $clickedLink.prop('hash'); // Gets the # part
      if (hash && $(hash).length) {
        newTabId = hash.substring(1);
      } else {
        return; // Cannot determine target
      }
    }

    if ($('#' + newTabId).length) {
      // Check if target content exists
      showTab(newTabId);

      // Optional: Update URL without reloading page for better history/bookmarking
      if (history.pushState) {
        // We should push the URL with the query parameter
        var newUrl = new URL(window.location);
        newUrl.searchParams.set('page', url.searchParams.get('page')); // Keep page param
        newUrl.searchParams.set('tab', targetSection); // Set the tab param
        newUrl.hash = ''; // Clear hash if used previously
        history.pushState({ tab: newTabId }, '', newUrl.toString());
      }
    }
  });

  // Optional: Handle browser back/forward button navigation for tabs
  $(window).on('popstate', function (event) {
    var state = event.originalEvent.state;
    var poppedUrlParams = new URLSearchParams(window.location.search);
    var poppedQueryTab = poppedUrlParams.get('tab');
    var poppedTabId = null;

    if (poppedQueryTab) {
      var potentialTabId = 'qcp-tab-' + poppedQueryTab.replace('qcp_', '').replace('_section', '');
      if ($('#' + potentialTabId).length) {
        poppedTabId = potentialTabId;
      }
    }

    showTab(poppedTabId || defaultTabId); // Show the tab from history or default
  });
});
