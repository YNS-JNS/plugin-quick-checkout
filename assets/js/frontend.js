/**
 * Quick Checkout Popup Frontend Script V1 (Modern UI/UX)
 * Version: 1.2.0 - Handles Placeholders, New Required Indicator, UI Refinements.
 */
jQuery(document).ready(function ($) {
  // --- Setup ---
  var config = qcp_params || {};
  if (typeof config.ajax_url === 'undefined' || typeof accounting === 'undefined') {
    console.error('QCP Error: Config or Accounting Lib missing.');
    $('.qcp-buy-now-button').hide();
    return;
  }
  var ajaxUrl = config.ajax_url;
  var nonce = config.nonce;
  var messages = {
    req: config.validation_messages?.required || 'Required.',
    phone: config.validation_messages?.phone_format_invalid || 'Invalid phone.',
    minQty: config.validation_messages?.min_quantity_error || 'Min 1.',
    numErr: config.validation_messages?.numeric_error || 'Invalid number.',
    selectOpts: config.variation_messages?.select_options || 'Select options.',
    varUnavailable: config.variation_messages?.unavailable || 'Unavailable.',
    varOOS: config.variation_messages?.out_of_stock || 'Out of stock.',
    addingOpts: config.variation_messages?.adding_options || 'Loading...',
    proceed: config.variation_messages?.variation_selected || 'Proceed',
    selectVar: config.variation_messages?.select_variation || 'Select Variation',
    couponErr: config.coupon_messages?.error || 'Coupon error.',
    couponApplied: config.coupon_messages?.applied || 'Applied.',
    couponRemoved: config.coupon_messages?.removed || 'Removed.',
    couponEnter: config.coupon_messages?.enter_code || 'Enter code.',
    applyBtn: config.coupon_messages?.apply_text || 'Apply',
    applyingBtn: config.coupon_messages?.applying_text || 'Applying...',
    removeBtn: config.coupon_messages?.remove_text || 'Remove',
    removingBtn: config.coupon_messages?.removing_text || 'Removing...',
  };
  var priceFormat = {
    symbol: config.price_format?.currency_symbol || '$',
    decimal: config.price_format?.decimal_separator || '.',
    thousand: config.price_format?.thousand_separator || ',',
    precision: config.price_format?.decimals ?? 2,
    format:
      (config.price_format?.currency_pos || 'left') === 'left'
        ? '%s%v'
        : config.price_format?.currency_pos === 'left_space'
        ? '%s %v'
        : config.price_format?.currency_pos === 'right_space'
        ? '%v %s'
        : '%v%s',
  };
  var placeholderImage = config.placeholder_image_url || '';
  var enableCoupons = !!config.enable_coupons;
  var enableVariations = !!config.enable_variations;
  var enableStats = !!config.enable_stats;
  var baseSubmitText = config.submit_button_text_base || 'Place Order';
  var totalLabel = config.total_label || 'Total';

  console.log('QCP Frontend Script Loaded v1.2.0');

  // --- Selectors ---
  var $body = $('body');
  var $overlay = $('#qcp-popup-overlay');
  var $popupWrap = $('#qcp-popup-wrap');
  if (!$overlay.length || !$popupWrap.length) {
    console.error('QCP Init Error: Popup elements missing.');
    $('.qcp-buy-now-button').hide();
    return;
  }
  var $views = $('.qcp-popup-view');
  var $loadingView = $('#qcp-loading-view');
  var $variationView = $('#qcp-variation-view');
  var $checkoutView = $('#qcp-checkout-view');
  var $successView = $('#qcp-success-view');
  var $processingOverlay = $('#qcp-processing-overlay');
  var $form = $('#qcp-checkout-form');
  var $formErrorsGlobal = $('#qcp-form-errors');
  var $allFormFields = $form.find('input:not([type="hidden"]), textarea, select');
  var $checkoutProductName = $('#qcp-product-name');
  var $checkoutProductPrice = $('#qcp-product-price');
  var $checkoutProductImage = $('#qcp-product-image');
  var $productIdInput = $('#qcp-product-id-input');
  var $variationIdInput = $('#qcp-variation-id-input');
  var $quantityInput = $('#qcp-quantity');
  var $qtyMinusBtn = $('.qcp-qty-minus');
  var $qtyPlusBtn = $('.qcp-qty-plus');
  var $couponInput = $('#qcp-coupon-code');
  var $applyCouponButton = $('#qcp-apply-coupon-button');
  var $removeCouponButton = $('#qcp-remove-coupon-button');
  var $couponMessages = $('#qcp-coupon-messages');
  var $appliedCouponInput = $('#qcp-applied-coupon-code');
  var $variationProductName = $('#qcp-variation-product-name');
  var $variationProductPrice = $('#qcp-variation-product-price');
  var $variationProductImage = $('#qcp-variation-product-image');
  var $variationStockStatus = $('#qcp-variation-stock-status');
  var $variationOptionsContainer = $('#qcp-variation-options');
  var $variationMessages = $('#qcp-variation-messages');
  var $selectVariationButton = $('#qcp-select-variation-button');
  var $submitButton = $('#qcp-submit-button');
  var $submitButtonText = $submitButton.find('.qcp-button-text');
  var $successMessageEl = $('#qcp-success-message');
  var $orderTotalValue = $('#qcp-order-total-value');
  var $orderTotalLabel = $('#qcp-order-total-label');

  // --- State ---
  var state = {
    productId: 0,
    variationId: 0,
    basePrice: 0,
    quantity: 1,
    productType: 'simple',
    variations: [],
    attributes: {},
    coupon: { code: '', amount: 0, type: '' },
    isSubmitting: false,
    isApplyingCoupon: false,
    isLoadingVariations: false,
    currentView: null,
  };

  // --- Helper Functions ---
  function formatPriceAccounting(price) {
    /* ... (inchangé depuis 1.1.0) ... */ try {
      return accounting.formatMoney(
        price,
        priceFormat.symbol,
        priceFormat.precision,
        priceFormat.thousand,
        priceFormat.decimal,
        priceFormat.format,
      );
    } catch (e) {
      console.error('Price Format Error:', e);
      var symbol = config.price_format?.currency_symbol || '$';
      var decimals = config.price_format?.decimals ?? 2;
      var formattedPrice = parseFloat(price).toFixed(decimals);
      return symbol + formattedPrice;
    }
  }
  function debounce(func, wait) {
    var t;
    return function () {
      var ctx = this,
        a = arguments;
      clearTimeout(t);
      t = setTimeout(() => {
        func.apply(ctx, a);
      }, wait);
    };
  }
  function setButtonLoadingState($button, isLoading) {
    if (!$button || !$button.length) return;
    if (isLoading) {
      $button.addClass('qcp-button--loading').prop('disabled', true);
    } else {
      $button.removeClass('qcp-button--loading').prop('disabled', false);
    }
  }
  function clearValidationErrors() {
    $formErrorsGlobal.hide().html('');
    $form.find('.qcp-input-error').removeClass('qcp-input-error');
    $form.find('.qcp-field-error-message').text('').hide();
  }
  function displayValidationErrors(errors) {
    /* ... (inchangé depuis 1.1.0, devrait bien gérer les erreurs) ... */ clearValidationErrors();
    var ge = [];
    var fe = null;
    var feId = null;
    if ($.isPlainObject(errors)) {
      $.each(errors, function (fid, msg) {
        if (fid === 'global' || fid === 'product_selection' || fid === 'global_variation') {
          ge.push(msg);
          return;
        }
        var $f = $('#' + fid);
        var $fw = $f.length
          ? $f.closest('.form-row, .woocommerce-input-wrapper, p')
          : $('#' + fid + '_field').length
          ? $('#' + fid + '_field')
          : null;
        if ($f.length) {
          $f.addClass('qcp-input-error');
          var $errMsgEl = $f.siblings('.qcp-field-error-message').first();
          if (!$errMsgEl.length && $fw && $fw.length) {
            $errMsgEl = $fw.find('.qcp-field-error-message').first();
          }
          if ($errMsgEl.length) {
            $errMsgEl.text(msg).show();
          } else {
            $f.after(
              '<span class="qcp-field-error-message" role="alert" aria-live="polite" style="display:block;">' +
                $('<div>').text(msg).html() +
                '</span>',
            );
          }
          if (!fe) {
            fe = $f;
            feId = fid;
          }
        } else {
          console.warn('QCP Val: Field target miss:', fid);
          ge.push(msg);
        }
      });
    } else if (Array.isArray(errors)) {
      ge = errors;
    }
    if (ge.length > 0) {
      var eh = '<ul class="woocommerce-error" role="alert">';
      ge.forEach(function (m) {
        eh += '<li>' + $('<div>').text(m).html() + '</li>';
      });
      eh += '</ul>';
      $formErrorsGlobal.html(eh).show();
      if (state.currentView === 'qcp-checkout-view') {
        $checkoutView.animate({ scrollTop: $formErrorsGlobal.position().top - 20 }, 300);
      }
    }
    if (fe) {
      console.log('Focusing first error:', feId);
      setTimeout(
        function () {
          fe.trigger('focus');
        },
        ge.length > 0 ? 350 : 50,
      );
      if (state.currentView === 'qcp-checkout-view' && ge.length === 0) {
        var scrollToPos = fe.closest('.form-row, p').length
          ? fe.closest('.form-row, p').position().top
          : fe.position().top;
        $checkoutView.animate({ scrollTop: $checkoutView.scrollTop() + scrollToPos - 20 }, 300);
      }
    }
  }
  function displayCouponMessage(message, type = 'notice') {
    var cls = 'woocommerce-message';
    if (type === 'error') cls = 'woocommerce-error';
    else if (type === 'success') cls += ' woocommerce-message--success';
    $couponMessages
      .html('<div class="' + cls + '" role="alert">' + $('<div>').text(message).html() + '</div>')
      .show();
  }
  function displayVariationMessage(message, type = 'notice') {
    var cls = 'woocommerce-message';
    if (type === 'error') cls = 'woocommerce-error';
    $variationMessages
      .html('<div class="' + cls + '" role="alert">' + $('<div>').text(message).html() + '</div>')
      .show();
  }
  function showPopupView(viewId) {
    console.log('QCP Switching view to:', viewId);
    var $targetView = $('#' + viewId);
    if ($targetView.length && state.currentView !== viewId) {
      var $currentActive = $views.filter('.qcp-view-active');
      state.currentView = viewId;
      if ($currentActive.length) {
        $currentActive
          .removeClass('qcp-view-active')
          .stop(true, true)
          .fadeOut(100, function () {
            $targetView.addClass('qcp-view-active').stop(true, true).fadeIn(150);
          });
      } else {
        $views.hide();
        $targetView.addClass('qcp-view-active').stop(true, true).fadeIn(150);
      }
      console.log('QCP View activated:', viewId);
    } else if (!$targetView.length) {
      console.error('QCP showPopupView Error: View element not found:', viewId);
    }
  }
  function updateSubmitButtonText(total) {
    if (!isNaN(total)) {
      var formattedTotal = formatPriceAccounting(total);
      var btnText = baseSubmitText + ' (' + formattedTotal + ')';
      $submitButtonText.html(btnText);
      $submitButton.val($('<div>').html(btnText).text());
    } else {
      $submitButtonText.text(baseSubmitText);
      $submitButton.val(baseSubmitText);
    }
  }
  function updateCheckoutPriceDisplay() {
    if (isNaN(state.quantity) || state.quantity < 1) state.quantity = 1;
    var unitPrice = state.basePrice;
    var subtotal = unitPrice * state.quantity;
    var total = subtotal;
    if (state.coupon.code && state.coupon.amount > 0) {
      var discount = 0;
      if (state.coupon.type === 'percent') {
        discount = subtotal * (state.coupon.amount / 100);
      } else {
        discount =
          state.coupon.type === 'fixed_cart'
            ? state.coupon.amount
            : state.coupon.amount * state.quantity;
      }
      discount = Math.min(discount, subtotal);
      total = subtotal - discount;
    }
    total = Math.max(0, total);
    $checkoutProductPrice.html(formatPriceAccounting(unitPrice));
    $orderTotalValue.html(formatPriceAccounting(total));
    updateSubmitButtonText(total);
  }
  var debouncedUpdateCheckoutPrice = debounce(updateCheckoutPriceDisplay, 150);
  function resetPopupState() {
    console.log('QCP Resetting Popup State');
    $form[0]?.reset();
    clearValidationErrors();
    $quantityInput.val(1);
    state = {
      productId: 0,
      variationId: 0,
      basePrice: 0,
      quantity: 1,
      productType: 'simple',
      variations: [],
      attributes: {},
      coupon: { code: '', amount: 0, type: '' },
      isSubmitting: false,
      isApplyingCoupon: false,
      isLoadingVariations: false,
      currentView: null,
    };
    $productIdInput.val('');
    $variationIdInput.val('');
    $appliedCouponInput.val('');
    $checkoutProductName.text('');
    $checkoutProductPrice.html('');
    $checkoutProductImage.attr('src', placeholderImage);
    $orderTotalValue.html('');
    $variationOptionsContainer.html(
      '<p class="qcp-variation-loading-text">' + messages.addingOpts + '</p>',
    );
    $variationMessages.hide().html('');
    $selectVariationButton.prop('disabled', true).text(messages.selectVar);
    $variationStockStatus.text('').removeClass('in-stock out-of-stock');
    resetCouponUIState(true);
    setButtonLoadingState($submitButton, false);
    $submitButtonText.text(baseSubmitText);
    $submitButton.val(baseSubmitText);
    $processingOverlay.removeClass('qcp-visible');
    $views.removeClass('qcp-view-active').hide();
  }

  // --- Core Logic ---
  function openPopup(productId, productType, data) {
    console.log('QCP openPopup START:', { productId, productType, data });
    if (!$overlay.length || !$popupWrap.length) {
      console.error('QCP FATAL: Popup elements missing.');
      alert('Error: Popup failed.');
      return;
    }
    resetPopupState();
    state.productId = productId;
    state.productType = productType;
    $overlay.removeClass('qcp-popup-hidden');
    $popupWrap.removeClass('qcp-popup-hidden');
    $body.addClass('qcp-popup-is-open');
    console.log('QCP Overlay/Wrap shown.');
    if (productType === 'variable' && enableVariations) {
      console.log('QCP Handling as Variable Product');
      showPopupView('qcp-loading-view');
      state.isLoadingVariations = true;
      loadVariationData(productId, data);
    } else if (productType === 'simple' || (productType === 'variable' && !enableVariations)) {
      console.log('QCP Handling as Simple Product');
      populateCheckoutForm(
        productId,
        0,
        data.productName,
        data.productPriceHtml,
        data.productImageUrl,
        data.productRawPrice,
      );
      showPopupView('qcp-checkout-view');
    } else {
      console.error('QCP: Unknown product type:', productType);
      showPopupView('qcp-checkout-view');
      displayValidationErrors({ global: 'This product cannot be purchased.' });
    }
    $popupWrap.scrollTop(0);
  }
  function loadVariationData(productId, baseData) {
    console.log('QCP Loading variations for:', productId);
    $variationProductName.text(baseData.productName);
    $variationProductPrice.html(baseData.productPriceHtml);
    $variationProductImage
      .attr('src', baseData.productImageUrl || placeholderImage)
      .data('parent-image', baseData.productImageUrl || placeholderImage);
    $variationStockStatus.text('');
    state.isLoadingVariations = true;
    $.ajax({
      type: 'POST',
      url: ajaxUrl,
      data: { action: 'qcp_get_variation_data', nonce: nonce, product_id: productId },
      dataType: 'json',
    })
      .done(function (response) {
        if (
          response &&
          response.success &&
          Array.isArray(response.data.variations) &&
          response.data.variations.length > 0
        ) {
          state.variations = response.data.variations;
          $variationOptionsContainer.html(response.data.attributes_html);
          if ($variationOptionsContainer.find('form.variations_form').length > 0) {
            initializeVariationForm();
            showPopupView('qcp-variation-view');
            console.log('QCP Variations loaded.');
          } else {
            console.error('QCP Variation Error: variations_form not found.');
            displayVariationMessage('Error loading options structure.', 'error');
            showPopupView('qcp-variation-view');
          }
        } else {
          var errorMsg = response?.data?.message || 'Could not load product options.';
          displayVariationMessage(errorMsg, 'error');
          showPopupView('qcp-variation-view');
          $variationOptionsContainer.html(
            '<p class="qcp-error-text">' + $('<div>').text(errorMsg).html() + '</p>',
          );
        }
      })
      .fail(function (j, t) {
        displayVariationMessage('Error loading options (' + t + ')', 'error');
        showPopupView('qcp-variation-view');
        $variationOptionsContainer.html('<p class="qcp-error-text">Error loading options.</p>');
      })
      .always(function () {
        state.isLoadingVariations = false;
      });
  }
  function initializeVariationForm() {
    var $vf = $variationOptionsContainer.find('form.variations_form');
    if (!$vf.length) {
      console.error('QCP Variation Init Error: variations_form not found.');
      return;
    }
    var $sel = $vf.find('.variations select');
    var $res = $vf.find('.reset_variations');
    $res.off('click.qcp').on('click.qcp', function (e) {
      e.preventDefault();
      $sel.val('').trigger('change');
      $variationMessages.hide().html('');
      $selectVariationButton.prop('disabled', true).text(messages.selectVar);
      $variationProductPrice.html('');
      $variationStockStatus.text('').removeClass('in-stock out-of-stock');
      $variationProductImage.attr(
        'src',
        $variationProductImage.data('parent-image') || placeholderImage,
      );
    });
    $sel.off('change.qcp').on('change.qcp', function () {
      state.attributes = {};
      var all = true;
      $sel.each(function () {
        var n = $(this).data('attribute_name') || $(this).attr('name');
        var v = $(this).val();
        if (v) state.attributes[n] = v;
        else all = false;
      });
      console.log('QCP Attributes changed:', state.attributes);
      if (all) {
        findMatchingVariation();
      } else {
        state.variationId = 0;
        $variationProductPrice.html('');
        $variationStockStatus.text('').removeClass('in-stock out-of-stock');
        $variationProductImage.attr(
          'src',
          $variationProductImage.data('parent-image') || placeholderImage,
        );
        $selectVariationButton.prop('disabled', true).text(messages.selectVar);
        $variationMessages.hide().html('');
      }
    });
    $sel.first().trigger('change');
  }
  function findMatchingVariation() {
    var match = null;
    if (!Array.isArray(state.variations) || state.variations.length === 0) {
      console.warn('QCP findMatchingVariation: No variations data.');
      updateVariationDisplay(null);
      return;
    }
    console.log('QCP Finding match for:', state.attributes);
    for (var i = 0; i < state.variations.length; i++) {
      var v = state.variations[i];
      var isMatch = true;
      var currentVariationAttributes = v.attributes;
      if (Object.keys(state.attributes).length !== Object.keys(currentVariationAttributes).length) {
        isMatch = false;
        continue;
      }
      for (var sa in state.attributes) {
        if (state.attributes.hasOwnProperty(sa)) {
          var sv = state.attributes[sa];
          var va = currentVariationAttributes[sa];
          if (typeof va === 'undefined' || (va !== '' && va !== sv)) {
            isMatch = false;
            break;
          }
        }
      }
      if (isMatch) {
        match = v;
        break;
      }
    }
    updateVariationDisplay(match);
  }
  function updateVariationDisplay(variation) {
    if (variation) {
      console.log('QCP Match found:', variation);
      $variationMessages.hide().html('');
      $variationProductImage.attr(
        'src',
        variation.image?.src || $variationProductImage.data('parent-image') || placeholderImage,
      );
      $variationProductPrice.html(variation.price_html || '');
      if (variation.is_in_stock) {
        $variationStockStatus
          .removeClass('out-of-stock')
          .addClass('in-stock')
          .html(variation.availability_html || 'In stock');
        $selectVariationButton.prop('disabled', false).text(messages.proceed);
        state.variationId = variation.variation_id;
        state.basePrice = parseFloat(variation.display_price) || 0;
      } else {
        $variationStockStatus
          .removeClass('in-stock')
          .addClass('out-of-stock')
          .html(variation.availability_html || messages.varOOS);
        $selectVariationButton.prop('disabled', true).text(messages.varOOS);
        state.variationId = 0;
        state.basePrice = 0;
      }
    } else {
      console.log('QCP No matching variation.');
      state.variationId = 0;
      state.basePrice = 0;
      $variationProductPrice.html('');
      $variationStockStatus.text('').removeClass('in-stock out-of-stock');
      $selectVariationButton.prop('disabled', true).text(messages.varUnavailable);
      displayVariationMessage(messages.varUnavailable, 'error');
      $variationProductImage.attr(
        'src',
        $variationProductImage.data('parent-image') || placeholderImage,
      );
    }
  }
  function populateCheckoutForm(productId, variationId, name, priceHtml, imageUrl, rawPrice) {
    $productIdInput.val(productId);
    $variationIdInput.val(variationId || 0);
    $checkoutProductName.text(name);
    $checkoutProductImage.attr('src', imageUrl || placeholderImage);
    state.basePrice = parseFloat(rawPrice) || 0;
    state.quantity = 1;
    $quantityInput.val(1);
    resetCouponUIState(true);
    $checkoutProductPrice.html(formatPriceAccounting(state.basePrice));
    updateCheckoutPriceDisplay();
  }
  function closePopups() {
    console.log('QCP closePopups called.');
    $overlay.addClass('qcp-popup-hidden');
    $popupWrap.addClass('qcp-popup-hidden');
    $body.removeClass('qcp-popup-is-open');
    setTimeout(resetPopupState, 300);
  }

  // --- Event Handlers ---
  $body.on('click.qcp', '.qcp-buy-now-button', function (e) {
    e.preventDefault();
    e.stopPropagation();
    console.log('QCP Buy Now Click Detected!');
    var $btn = $(this);
    var pid = $btn.data('product-id');
    var ptype = $btn.data('product-type') || 'simple';
    console.log('QCP Detected Product Type:', ptype);
    var data = {
      productName: $btn.data('product-name'),
      productPriceHtml: $btn.data('product-price-html'),
      productImageUrl: $btn.data('product-image-url'),
      productRawPrice: $btn.data('product-price'),
    };
    if (!pid || !data.productName || typeof data.productRawPrice === 'undefined') {
      console.error('QCP Error: Missing data on button (ID, Name, or Price).', data);
      alert('Error: Product info missing.');
      return;
    }
    openPopup(pid, ptype, data);
    trackStat('click', pid);
  });
  $selectVariationButton.on('click.qcp', function (e) {
    e.preventDefault();
    if (state.variationId > 0) {
      var selVar = state.variations.find(function (v) {
        return v.variation_id === state.variationId;
      });
      if (selVar) {
        populateCheckoutForm(
          state.productId,
          state.variationId,
          $variationProductName.text(),
          selVar.price_html,
          selVar.image?.src,
          selVar.display_price,
        );
        showPopupView('qcp-checkout-view');
        $checkoutView.scrollTop(0);
      } else {
        displayVariationMessage('Error finding selected variation.', 'error');
      }
    } else {
      displayVariationMessage(messages.selectOpts, 'error');
    }
  });
  $qtyMinusBtn.on('click.qcp', function (e) {
    e.preventDefault();
    var v = parseInt($quantityInput.val(), 10);
    if (!isNaN(v) && v > 1) {
      $quantityInput.val(v - 1).trigger('input');
    }
  });
  $qtyPlusBtn.on('click.qcp', function (e) {
    e.preventDefault();
    var v = parseInt($quantityInput.val(), 10);
    $quantityInput.val(isNaN(v) ? 1 : v + 1).trigger('input');
  });
  $quantityInput.on('change.qcp input.qcp', function () {
    var q = parseInt($(this).val(), 10);
    var min = parseInt($(this).attr('min')) || 1;
    if (isNaN(q) || q < min) {
      q = min;
      $(this).val(q);
    }
    state.quantity = q;
    debouncedUpdateCheckoutPrice();
  });
  $popupWrap.on('click.qcp', '.qcp-popup-close', function (e) {
    e.preventDefault();
    closePopups();
  });
  $overlay.on('click.qcp', function (e) {
    if ($(e.target).is('#qcp-popup-overlay')) {
      e.preventDefault();
      closePopups();
    }
  });
  if (enableCoupons) {
    $applyCouponButton.on('click.qcp', function (e) {
      e.preventDefault();
      applyCoupon();
    });
    $removeCouponButton.on('click.qcp', function (e) {
      e.preventDefault();
      removeCoupon();
    });
  }

  // --- Checkout Form Submit ---
  $form.on('submit.qcp', function (e) {
    /* ... (inchangé, validation frontend via `[required]` est gérée) ... */ e.preventDefault();
    if (state.isSubmitting) return;
    console.log('QCP Form Submit triggered.');
    clearValidationErrors();
    var errors = {};
    var firstErrorField = null;
    $form.find('[required]:visible').each(function () {
      var $i = $(this);
      var v = $.trim($i.val());
      var id = $i.attr('id');
      if (!v) {
        errors[id] = messages.req;
        if (!firstErrorField) firstErrorField = $i;
      } else if ($i.attr('type') === 'tel' && !isValidPhone(v)) {
        errors[id] = messages.phone;
        if (!firstErrorField) firstErrorField = $i;
      }
    });
    var q = parseInt($quantityInput.val(), 10);
    if (isNaN(q) || q < 1) {
      errors[$quantityInput.attr('id') || 'qcp-quantity'] = messages.minQty;
      if (!firstErrorField) firstErrorField = $quantityInput;
    }
    if (state.productType === 'variable' && state.variationId === 0 && enableVariations) {
      errors['global_variation'] = messages.selectOpts;
    }
    if (Object.keys(errors).length > 0) {
      console.log('QCP Validation Failed:', errors);
      displayValidationErrors(errors);
      return;
    }
    $processingOverlay.addClass('qcp-visible').show();
    state.isSubmitting = true;
    setButtonLoadingState($submitButton, true);
    var formData = $form.serialize();
    console.log('QCP Form Data for Submit:', formData);
    $.ajax({
      type: 'POST',
      url: ajaxUrl,
      data: { action: 'qcp_submit_checkout', nonce: nonce, form_data: formData },
      dataType: 'json',
    })
      .done(function (response) {
        if (response && response.success) {
          showPopupView('qcp-success-view');
          $successMessageEl.html(response.data.message || config.thank_you_message);
          $popupWrap.scrollTop(0);
          if (typeof trackPurchase === 'function' && response.data.order_details)
            trackPurchase(response.data.order_details);
        } else {
          $processingOverlay.removeClass('qcp-visible').hide();
          displayValidationErrors(
            response?.data?.errors || { global: 'An unknown server error occurred.' },
          );
        }
      })
      .fail(function (jqXHR, textStatus) {
        $processingOverlay.removeClass('qcp-visible').hide();
        console.error('QCP AJAX Submit Error:', textStatus, jqXHR.status, jqXHR.responseText);
        displayValidationErrors({
          global: 'AJAX Error (' + jqXHR.status + ' ' + textStatus + '). Please try again.',
        });
      })
      .always(function () {
        if ($processingOverlay.hasClass('qcp-visible')) {
          $processingOverlay.removeClass('qcp-visible').hide();
        }
        setButtonLoadingState($submitButton, false);
        state.isSubmitting = false;
      });
  });

  // --- Coupon Functions ---
  function applyCoupon() {
    if (state.isApplyingCoupon) return;
    var c = $.trim($couponInput.val());
    if (!c) {
      displayCouponMessage(messages.couponEnter, 'error');
      return;
    }
    state.isApplyingCoupon = true;
    setCouponLoadingState(true);
    $couponMessages.hide().html('');
    $.ajax({
      type: 'POST',
      url: ajaxUrl,
      dataType: 'json',
      data: {
        action: 'qcp_apply_coupon',
        nonce: nonce,
        coupon_code: c,
        product_id: state.productId,
        variation_id: state.variationId,
        quantity: state.quantity,
      },
    })
      .done(function (r) {
        if (r && r.success) {
          displayCouponMessage(r.data.message || messages.couponApplied, 'success');
          $appliedCouponInput.val(c);
          state.coupon = {
            code: c,
            amount: parseFloat(r.data.discount_amount) || 0,
            type: r.data.discount_type || 'fixed_cart',
          };
          updateCheckoutPriceDisplay();
          toggleCouponButtons(true);
          $couponInput.prop('disabled', true);
        } else {
          displayCouponMessage(r?.data?.message || messages.couponErr, 'error');
          resetCouponUIState(false);
        }
      })
      .fail(function (j, t) {
        displayCouponMessage(messages.couponErr + ' (' + t + ')', 'error');
        resetCouponUIState(false);
      })
      .always(function () {
        setCouponLoadingState(false);
        state.isApplyingCoupon = false;
      });
  }
  function removeCoupon() {
    if (state.isApplyingCoupon) return;
    var ac = state.coupon.code;
    if (!ac) return;
    state.isApplyingCoupon = true;
    setCouponLoadingState(true, true);
    $couponMessages.hide().html('');
    setTimeout(function () {
      displayCouponMessage(messages.couponRemoved, 'notice');
      state.coupon = { code: '', amount: 0, type: '' };
      resetCouponUIState(true);
      updateCheckoutPriceDisplay();
      state.isApplyingCoupon = false;
      setCouponLoadingState(false, true);
    }, 200);
  }
  function resetCouponUIState(clearInput = true) {
    if (clearInput) $couponInput.val('');
    $appliedCouponInput.val('');
    $couponInput.prop('disabled', false);
    toggleCouponButtons(false);
  }
  function toggleCouponButtons(isApplied) {
    $applyCouponButton.toggle(!isApplied).prop('disabled', state.isApplyingCoupon);
    $removeCouponButton.toggle(isApplied).prop('disabled', state.isApplyingCoupon);
  }
  function setCouponLoadingState(isLoading, isRemoving = false) {
    var $b = isRemoving ? $removeCouponButton : $applyCouponButton;
    var $o = isRemoving ? $applyCouponButton : $removeCouponButton;
    var lt = isRemoving ? messages.removingBtn : messages.applyingBtn;
    var dt = isRemoving ? messages.removeBtn : messages.applyBtn;
    setButtonLoadingState($b, isLoading);
    $b.find('.qcp-button-text').text(isLoading ? lt : dt);
    $o.prop('disabled', isLoading);
  }

  // --- Validation ---
  function isValidPhone(phone) {
    if (!phone) return false;
    var d = (phone.match(/\d/g) || []).length;
    return /^[0-9\s\-\+\(\)]+$/.test(phone) && d >= 7;
  }

  // --- Analytics & Stats ---
  function trackPurchase(details) {
    console.log('QCP Track Purchase:', details);
    if (!details) return;
    var qty = details.quantity || 1;
    if (typeof fbq !== 'undefined' && config.fb_pixel_id) {
      try {
        fbq(
          'track',
          'Purchase',
          {
            content_ids: [details.product_id.toString()],
            content_type: 'product',
            value: parseFloat(details.total) || 0,
            currency: details.currency || 'USD',
            num_items: qty,
          },
          details.order_id ? { eventID: 'qcp_' + details.order_id.toString() } : {},
        );
      } catch (e) {
        console.error('FB Err:', e);
      }
    }
    if (typeof gtag !== 'undefined' && config.ga_id) {
      try {
        gtag('event', 'purchase', {
          transaction_id: details.order_id.toString(),
          value: parseFloat(details.total) || 0,
          currency: details.currency || 'USD',
          items: [
            {
              item_id: details.product_id.toString(),
              item_name: details.product_name || 'Product',
              price: parseFloat(details.item_price) || 0,
              quantity: qty,
            },
          ],
        });
      } catch (e) {
        console.error('GA Err:', e);
      }
    }
  }

  function trackStat(type, id) {
    if (!enableStats || !id) {
      console.log('QCP Stat Tracking skipped (Disabled or Invalid ID). Type:', type, 'ID:', id);
      return;
    }
    console.log('QCP Track Stat Request Sent. Type:', type, 'ID:', id);
    $.ajax({
      type: 'POST',
      url: ajaxUrl,
      data: { action: 'qcp_track_stat', nonce: nonce, stat_type: type, product_id: id },
      dataType: 'json',
    })
      .done(function (response) {
        if (response && response.success) {
          console.log('QCP Stat Track Success:', response.data?.message);
        } else {
          console.warn('QCP Stat Track Server Response:', response?.data?.message || 'No message');
        }
      })
      .fail(function (j, t) {
        console.error('QCP Stat Track AJAX Error:', t);
      });
  }
}); // End jQuery
