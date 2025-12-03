jQuery(document).ready(function ($) {
  const itemsList = $("#ashwab-items-list");
  const resultsList = $("#ashwab-results");
  const modal = $("#ashwab-command-menu");
  const searchInput = $("#ashwab-search");
  const excludedUsersList = $("#ashwab-excluded-users-list");
  const hiddenWidgetsList = $("#ashwab-hidden-widgets-list");
  const cssElementsList = $("#ashwab-css-elements-list");

  // Initial Render
  renderItems(ashwabData.items);
  renderExcludedUsers(ashwabData.excludedUsers);
  renderHiddenWidgets(ashwabData.hiddenWidgets);
  renderCssElements(ashwabData.cssHideElements);

  // Initialize Select2 for page dropdown
  if ($.fn.select2) {
    $("#ashwab-redirect-page").select2({
      placeholder: "-- Select a Page --",
      allowClear: true,
      width: "100%",
    });
  }

  // Handle redirect type change
  $("#ashwab-redirect-type").on("change", function () {
    const selectedType = $(this).val();

    // Hide all value rows first
    $(".ashwab-redirect-value-row").hide();

    // Show the appropriate row based on selection
    if (selectedType === "custom_url") {
      $("#ashwab-redirect-url-row").show();
    } else if (selectedType === "page_id") {
      $("#ashwab-redirect-page-row").show();
    }
    // For 'default', no additional field is needed
  });

  // Open Modal
  $("#ashwab-add-btn").on("click", function () {
    modal.show();
    searchInput.focus();
    renderResults(ashwabData.availableItems);
  });

  // Close Modal
  $(".ashwab-close").on("click", function () {
    modal.hide();
  });

  $(window).on("click", function (event) {
    if (event.target == modal[0]) {
      modal.hide();
    }
  });

  // Search
  searchInput.on("input", function () {
    const query = $(this).val().toLowerCase();
    const filtered = ashwabData.availableItems.filter(
      (item) =>
        item.label.toLowerCase().includes(query) ||
        item.value.toLowerCase().includes(query)
    );
    renderResults(filtered);
  });

  // Add Item
  resultsList.on("click", "li", function () {
    const item = $(this).data("item");

    $.post(
      ashwabData.ajaxUrl,
      {
        action: "ashwab_save_item",
        nonce: ashwabData.nonce,
        item: item,
      },
      function (response) {
        if (response.success) {
          ashwabData.items = response.data;
          renderItems(ashwabData.items);
          modal.hide();
        } else {
          alert("Error adding item");
        }
      }
    );
  });

  // Remove Item
  itemsList.on("click", ".delete-btn", function () {
    const value = $(this).data("value");
    if (!confirm("Are you sure?")) return;

    $.post(
      ashwabData.ajaxUrl,
      {
        action: "ashwab_remove_item",
        nonce: ashwabData.nonce,
        value: value,
      },
      function (response) {
        if (response.success) {
          ashwabData.items = response.data;
          renderItems(ashwabData.items);
        } else {
          alert("Error removing item");
        }
      }
    );
  });

  // Add Excluded User
  $("#ashwab-add-excluded-user").on("click", function () {
    const userId = $("#ashwab-excluded-user-id").val();
    if (!userId) return;

    $.post(
      ashwabData.ajaxUrl,
      {
        action: "ashwab_save_excluded_user",
        nonce: ashwabData.nonce,
        user_id: userId,
      },
      function (response) {
        if (response.success) {
          ashwabData.excludedUsers = response.data;
          renderExcludedUsers(ashwabData.excludedUsers);
          $("#ashwab-excluded-user-id").val("");
        } else {
          alert("Error adding user");
        }
      }
    );
  });

  // Remove Excluded User
  excludedUsersList.on("click", ".remove-user", function () {
    const userId = $(this).data("id");
    if (!confirm("Are you sure?")) return;

    $.post(
      ashwabData.ajaxUrl,
      {
        action: "ashwab_remove_excluded_user",
        nonce: ashwabData.nonce,
        user_id: userId,
      },
      function (response) {
        if (response.success) {
          ashwabData.excludedUsers = response.data;
          renderExcludedUsers(ashwabData.excludedUsers);
        } else {
          alert("Error removing user");
        }
      }
    );
  });

  // Add Hidden Widget
  $("#ashwab-add-hidden-widget").on("click", function () {
    const widgetId = $("#ashwab-hidden-widget-id").val();
    if (!widgetId) return;

    $.post(
      ashwabData.ajaxUrl,
      {
        action: "ashwab_save_hidden_widget",
        nonce: ashwabData.nonce,
        widget_id: widgetId,
      },
      function (response) {
        if (response.success) {
          ashwabData.hiddenWidgets = response.data;
          renderHiddenWidgets(ashwabData.hiddenWidgets);
          $("#ashwab-hidden-widget-id").val("");
        } else {
          alert("Error adding widget");
        }
      }
    );
  });

  // Remove Hidden Widget
  hiddenWidgetsList.on("click", ".remove-widget", function () {
    const widgetId = $(this).data("id");
    if (!confirm("Are you sure?")) return;

    $.post(
      ashwabData.ajaxUrl,
      {
        action: "ashwab_remove_hidden_widget",
        nonce: ashwabData.nonce,
        widget_id: widgetId,
      },
      function (response) {
        if (response.success) {
          ashwabData.hiddenWidgets = response.data;
          renderHiddenWidgets(ashwabData.hiddenWidgets);
        } else {
          alert("Error removing widget");
        }
      }
    );
  });

  // Add CSS Element
  $("#ashwab-add-css-element").on("click", function () {
    const element = $("#ashwab-css-element").val();
    if (!element) return;

    // Validation
    if (!element.startsWith(".") && !element.startsWith("#")) {
      alert("Invalid format. Must start with . or #");
      return;
    }

    $.post(
      ashwabData.ajaxUrl,
      {
        action: "ashwab_save_css_element",
        nonce: ashwabData.nonce,
        element: element,
      },
      function (response) {
        if (response.success) {
          ashwabData.cssHideElements = response.data;
          renderCssElements(ashwabData.cssHideElements);
          $("#ashwab-css-element").val("");
        } else {
          alert(response.data || "Error adding element");
        }
      }
    );
  });

  // Remove CSS Element
  cssElementsList.on("click", ".remove-css-element", function () {
    const element = $(this).data("id");
    if (!confirm("Are you sure?")) return;

    $.post(
      ashwabData.ajaxUrl,
      {
        action: "ashwab_remove_css_element",
        nonce: ashwabData.nonce,
        element: element,
      },
      function (response) {
        if (response.success) {
          ashwabData.cssHideElements = response.data;
          renderCssElements(ashwabData.cssHideElements);
        } else {
          alert("Error removing element");
        }
      }
    );
  });

  // Regenerate CSS
  $("#ashwab-regenerate-css").on("click", function () {
    const btn = $(this);
    btn.prop("disabled", true).text("Regenerating...");

    $.post(
      ashwabData.ajaxUrl,
      {
        action: "ashwab_regenerate_css",
        nonce: ashwabData.nonce,
      },
      function (response) {
        btn.prop("disabled", false).text("Save & Regenerate CSS");
        if (response.success) {
          alert("CSS Regenerated Successfully!");
        } else {
          alert(response.data || "Error regenerating CSS");
        }
      }
    );
  });

  // Save Settings
  $(".ashwab-save-settings").on("click", function () {
    const btn = $(this);
    btn.prop("disabled", true).text("Saving...");

    const redirectType = $("#ashwab-redirect-type").val();
    let redirectValue = "";

    // Get the appropriate value based on redirect type
    if (redirectType === "custom_url") {
      redirectValue = $("#ashwab-redirect-url").val();
    } else if (redirectType === "page_id") {
      redirectValue = $("#ashwab-redirect-page").val();
    }

    const settings = {
      redirect: {
        type: redirectType,
        value: redirectValue,
      },
      hideNotices: $("#ashwab-hide-notices").is(":checked"),
      hidePlugin: $("#ashwab-hide-plugin").is(":checked"),
    };

    $.post(
      ashwabData.ajaxUrl,
      {
        action: "ashwab_save_settings",
        nonce: ashwabData.nonce,
        settings: settings,
      },
      function (response) {
        btn.prop("disabled", false).text("Save Changes");
        if (response.success) {
          alert("Settings saved!");
        } else {
          alert("Error saving settings");
        }
      }
    );
  });

  // Clear Logs
  $("#ashwab-clear-logs").on("click", function () {
    if (!confirm("Are you sure you want to clear all logs?")) return;

    $.post(
      ashwabData.ajaxUrl,
      {
        action: "ashwab_clear_logs",
        nonce: ashwabData.nonce,
      },
      function (response) {
        if (response.success) {
          location.reload();
        } else {
          alert("Error clearing logs");
        }
      }
    );
  });

  // Export Settings
  $("#ashwab-export-settings").on("click", function () {
    const btn = $(this);
    btn.prop("disabled", true).text("Exporting...");

    $.post(
      ashwabData.ajaxUrl,
      {
        action: "ashwab_export_settings",
        nonce: ashwabData.nonce,
      },
      function (response) {
        btn.prop("disabled", false).text("Export Settings");
        if (response.success) {
          // Create download link
          const dataStr = JSON.stringify(response.data, null, 2);
          const dataBlob = new Blob([dataStr], { type: "application/json" });
          const url = URL.createObjectURL(dataBlob);
          const link = document.createElement("a");
          link.href = url;
          const timestamp = new Date().toISOString().replace(/[:.]/g, "-").slice(0, -5);
          link.download = `ashwab-access-hide-export-${timestamp}.json`;
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
          URL.revokeObjectURL(url);
          alert("Settings exported successfully!");
        } else {
          alert(response.data || "Error exporting settings");
        }
      }
    );
  });

  // Import Settings
  $("#ashwab-import-settings").on("click", function () {
    const fileInput = $("#ashwab-import-file")[0];
    if (!fileInput.files || fileInput.files.length === 0) {
      alert("Please select a JSON file to import");
      return;
    }

    const file = fileInput.files[0];
    if (file.type !== "application/json" && !file.name.endsWith(".json")) {
      alert("Please select a valid JSON file");
      return;
    }

    const btn = $(this);
    btn.prop("disabled", true).text("Importing...");

    const reader = new FileReader();
    reader.onload = function (e) {
      const importData = e.target.result;
      
      // Validate JSON
      try {
        JSON.parse(importData);
      } catch (err) {
        btn.prop("disabled", false).text("Import Settings");
        alert("Invalid JSON file: " + err.message);
        return;
      }

      $.post(
        ashwabData.ajaxUrl,
        {
          action: "ashwab_import_settings",
          nonce: ashwabData.nonce,
          import_data: importData,
        },
        function (response) {
          btn.prop("disabled", false).text("Import Settings");
          if (response.success) {
            alert("Settings imported successfully! The page will reload to show updated settings.");
            // Reload page to refresh all data
            location.reload();
          } else {
            alert(response.data || "Error importing settings");
          }
        }
      );
    };
    reader.onerror = function () {
      btn.prop("disabled", false).text("Import Settings");
      alert("Error reading file");
    };
    reader.readAsText(file);
  });

  function renderItems(items) {
    itemsList.empty();
    if (items.length === 0) {
      itemsList.append(
        '<tr><td colspan="4">No restricted items found.</td></tr>'
      );
      return;
    }
    items.forEach((item) => {
      itemsList.append(`
				<tr>
					<td>${item.type}</td>
					<td>${item.label}</td>
					<td>${item.value}</td>
					<td><span class="delete-btn" data-value="${item.value}">Remove</span></td>
				</tr>
			`);
    });
  }

  function renderResults(items) {
    resultsList.empty();
    items.forEach((item) => {
      const li = $("<li>").data("item", item);
      li.append(
        `<span>${item.label}</span> <span class="type-badge">${item.type}</span>`
      );
      resultsList.append(li);
    });
  }

  function renderExcludedUsers(users) {
    excludedUsersList.empty();
    users.forEach((userId) => {
      excludedUsersList.append(`
				<li>User ID: ${userId} <span class="remove-user" data-id="${userId}">&times;</span></li>
			`);
    });
  }

  function renderHiddenWidgets(widgets) {
    hiddenWidgetsList.empty();
    if (!widgets || widgets.length === 0) {
      hiddenWidgetsList.append("<li>No hidden widgets.</li>");
      return;
    }
    widgets.forEach((widgetId) => {
      hiddenWidgetsList.append(`
				<li>${widgetId} <span class="remove-widget" data-id="${widgetId}" style="color:red; cursor:pointer; margin-left:10px;">&times; Remove</span></li>
			`);
    });
  }

  function renderCssElements(elements) {
    cssElementsList.empty();
    if (!elements || elements.length === 0) {
      cssElementsList.append("<li>No hidden CSS elements.</li>");
      return;
    }
    elements.forEach((element) => {
      cssElementsList.append(`
				<li>${element} <span class="remove-css-element" data-id="${element}" style="color:red; cursor:pointer; margin-left:10px;">&times; Remove</span></li>
			`);
    });
  }
});
