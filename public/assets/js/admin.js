document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-confirm-access-state]").forEach((form) => {
    form.addEventListener("submit", (event) => {
      if (!window.confirm("Confirmer le changement d’état de ce compte ?")) {
        event.preventDefault();
      }
    });
  });

  const employeeSelect = document.querySelector("[data-rh-employee-select]");
  const employeePreview = document.querySelector("[data-rh-preview]");
  const updateEmployeePreview = () => {
    if (!employeeSelect || !employeePreview) return;
    const option = employeeSelect.selectedOptions[0];
    const hasEmployee = Boolean(option?.value);
    employeePreview.hidden = !hasEmployee;
    if (!hasEmployee) return;

    ["name", "number", "email", "phone", "service", "function"].forEach(
      (field) => {
        const target = employeePreview.querySelector(
          `[data-rh-field="${field}"]`,
        );
        if (target)
          target.textContent = option.dataset[field] || "Non renseigné";
      },
    );
  };
  employeeSelect?.addEventListener("change", updateEmployeePreview);
  updateEmployeePreview();

  const adminProfile = document.querySelector("[data-admin-profile]");
  const initialPermissions = document.querySelector(
    "[data-initial-permissions]",
  );
  const updatePermissionVisibility = () => {
    if (initialPermissions && adminProfile) {
      initialPermissions.hidden = adminProfile.checked;
    }
  };
  adminProfile?.addEventListener("change", updatePermissionVisibility);
  updatePermissionVisibility();

  const permissionRows = Array.from(
    document.querySelectorAll("[data-permission-row]"),
  );

  permissionRows.forEach((row) => {
    const read = row.querySelector('[data-action="view"]');
    row
      .querySelectorAll('[data-action]:not([data-action="view"])')
      .forEach((checkbox) => {
        checkbox.addEventListener("change", () => {
          if (checkbox.checked && read) read.checked = true;
        });
      });
  });

  document
    .querySelector("[data-permissions-clear]")
    ?.addEventListener("click", () => {
      permissionRows.forEach((row) => {
        row.querySelectorAll("input[type=checkbox]").forEach((input) => {
          input.checked = false;
        });
      });
    });

  document
    .querySelector("[data-permissions-read]")
    ?.addEventListener("click", () => {
      permissionRows.forEach((row) => {
        row.querySelectorAll("input[type=checkbox]").forEach((input) => {
          input.checked = input.dataset.action === "view";
        });
      });
    });

  document
    .querySelector("[data-permissions-all]")
    ?.addEventListener("click", () => {
      permissionRows.forEach((row) => {
        row.querySelectorAll("input[type=checkbox]").forEach((input) => {
          input.checked = true;
        });
      });
    });
});
