document.addEventListener("DOMContentLoaded", () => {
  const sidebar = document.getElementById("moduleSidebar");
  const menuButton = document.querySelector("[data-module-menu]");

  menuButton?.addEventListener("click", () => {
    sidebar?.classList.toggle("is-open");
  });

  document.querySelectorAll("[data-coming-soon]").forEach((link) => {
    link.addEventListener("click", (event) => event.preventDefault());
  });

  const childrenInput = document.querySelector("[data-children-count]");
  const childrenContainer = document.querySelector("[data-child-documents]");
  const renderChildren = () => {
    if (!childrenInput || !childrenContainer) return;
    const count = Math.max(
      0,
      Math.min(20, parseInt(childrenInput.value || "0", 10)),
    );
    childrenContainer.innerHTML = "";
    if (count === 0) return;
    const title = document.createElement("h3");
    title.textContent = "Extraits de naissance des enfants";
    childrenContainer.appendChild(title);
    for (let i = 1; i <= count; i += 1) {
      const label = document.createElement("label");
      label.className = "finea-dropzone finea-child-dropzone";
      label.setAttribute("data-finea-dropzone", "");
      label.innerHTML = `
        <input type="file" name="child_birth_certificates[]" accept="image/*,.pdf" required>
        <span class="finea-dropzone-icon">⇪</span>
        <strong>Enfant ${i}</strong>
        <span>Extrait de naissance obligatoire pour cet enfant.</span>
        <div class="finea-file-preview" data-finea-file-preview></div>`;
      childrenContainer.appendChild(label);
    }
    window.FineaComponents?.init?.();
  };
  childrenInput?.addEventListener("input", renderChildren);
  renderChildren();

  const featureLabel = (feature) => {
    const properties = feature?.properties || {};
    const parts = [
      properties.name,
      properties.street,
      properties.district,
      properties.city,
      properties.state,
      properties.country,
    ].filter(
      (value, index, values) => value && values.indexOf(value) === index,
    );

    return parts.join(", ") || "Position sélectionnée";
  };

  document.querySelectorAll("[data-geolocation]").forEach((component) => {
    const trigger = component.querySelector("[data-geolocation-trigger]");
    const search = component.querySelector("[data-geolocation-search]");
    const results = component.querySelector("[data-geolocation-results]");
    const mapContainer = component.querySelector("[data-geolocation-map]");
    const latitude = component.querySelector("[data-geolocation-latitude]");
    const longitude = component.querySelector("[data-geolocation-longitude]");
    const address = component.querySelector("[data-geolocation-address]");
    const status = component.querySelector("[data-geolocation-status]");
    const searchEndpoint = component.dataset.searchEndpoint;
    const reverseEndpoint = component.dataset.reverseEndpoint;
    const defaultPosition = [5.359952, -4.008256];
    const initialLatitude = Number.parseFloat(latitude?.value || "");
    const initialLongitude = Number.parseFloat(longitude?.value || "");
    const hasInitialPosition =
      Number.isFinite(initialLatitude) && Number.isFinite(initialLongitude);
    const initialPosition = hasInitialPosition
      ? [initialLatitude, initialLongitude]
      : defaultPosition;
    let map = null;
    let marker = null;
    let searchTimer = null;
    let searchController = null;
    let reverseController = null;

    const hideResults = () => {
      if (!results) return;
      results.hidden = true;
      results.replaceChildren();
    };

    const setPosition = (lat, lon, label = "", center = true) => {
      if (latitude) latitude.value = Number(lat).toFixed(7);
      if (longitude) longitude.value = Number(lon).toFixed(7);
      if (label) {
        if (address) address.value = label;
        if (search) search.value = label;
      }

      if (map && window.L) {
        if (!marker) {
          marker = window.L.marker([lat, lon], { draggable: true }).addTo(map);
          marker.on("dragend", () => {
            const position = marker.getLatLng();
            setPosition(position.lat, position.lng, "", false);
            reversePosition(position.lat, position.lng);
          });
        } else {
          marker.setLatLng([lat, lon]);
        }
        if (center) map.setView([lat, lon], 16);
      }

      if (status) {
        status.textContent = label
          ? `Position sélectionnée : ${label}`
          : "Position sélectionnée. Recherche de l’adresse…";
      }
    };

    const reversePosition = async (lat, lon) => {
      if (!reverseEndpoint) return;
      reverseController?.abort();
      reverseController = new AbortController();

      try {
        const url = new URL(reverseEndpoint);
        url.searchParams.set("lat", lat);
        url.searchParams.set("lon", lon);
        url.searchParams.set("lang", "fr");
        const response = await fetch(url, { signal: reverseController.signal });
        if (!response.ok) throw new Error("reverse-geocoding-failed");
        const payload = await response.json();
        const feature = payload.features?.[0];
        const label = feature ? featureLabel(feature) : "Position sur la carte";
        setPosition(lat, lon, label, false);
      } catch (error) {
        if (error.name === "AbortError") return;
        if (status)
          status.textContent =
            "Position sélectionnée. L’adresse exacte n’a pas pu être chargée.";
      }
    };

    const selectFeature = (feature) => {
      const coordinates = feature?.geometry?.coordinates || [];
      const lon = Number(coordinates[0]);
      const lat = Number(coordinates[1]);
      if (!Number.isFinite(lat) || !Number.isFinite(lon)) return;
      setPosition(lat, lon, featureLabel(feature));
      hideResults();
    };

    const renderResults = (features) => {
      if (!results) return;
      results.replaceChildren();

      if (features.length === 0) {
        const empty = document.createElement("p");
        empty.className = "finea-geolocation-empty";
        empty.textContent =
          "Aucune position trouvée. Précisez la rue, le quartier ou la ville.";
        results.appendChild(empty);
      } else {
        features.forEach((feature) => {
          const option = document.createElement("button");
          option.type = "button";
          option.className = "finea-geolocation-result";
          option.setAttribute("role", "option");

          const title = document.createElement("strong");
          title.textContent = feature?.properties?.name || "Position";
          const detail = document.createElement("small");
          detail.textContent = featureLabel(feature);
          option.append(title, detail);
          option.addEventListener("click", () => selectFeature(feature));
          results.appendChild(option);
        });
      }

      results.hidden = false;
    };

    const searchPlaces = async (query) => {
      if (!searchEndpoint || query.length < 3) {
        hideResults();
        return;
      }

      searchController?.abort();
      searchController = new AbortController();
      if (status) status.textContent = "Recherche des positions disponibles…";

      try {
        const url = new URL(searchEndpoint);
        url.searchParams.set("q", query);
        url.searchParams.set("limit", "8");
        url.searchParams.set("lang", "fr");
        const currentLat = Number.parseFloat(latitude?.value || "");
        const currentLon = Number.parseFloat(longitude?.value || "");
        if (Number.isFinite(currentLat) && Number.isFinite(currentLon)) {
          url.searchParams.set("lat", currentLat);
          url.searchParams.set("lon", currentLon);
        }

        const response = await fetch(url, { signal: searchController.signal });
        if (!response.ok) throw new Error("place-search-failed");
        const payload = await response.json();
        renderResults(payload.features || []);
        if (status)
          status.textContent = `${payload.features?.length || 0} position(s) disponible(s).`;
      } catch (error) {
        if (error.name === "AbortError") return;
        hideResults();
        if (status)
          status.textContent =
            "La recherche de positions est momentanément indisponible.";
      }
    };

    if (mapContainer && window.L) {
      map = window.L.map(mapContainer, { scrollWheelZoom: false }).setView(
        initialPosition,
        hasInitialPosition ? 16 : 12,
      );
      window.L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution:
          '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      }).addTo(map);
      map.on("click", (event) => {
        setPosition(event.latlng.lat, event.latlng.lng, "", false);
        reversePosition(event.latlng.lat, event.latlng.lng);
      });
      if (hasInitialPosition) {
        setPosition(
          initialLatitude,
          initialLongitude,
          address?.value || "",
          false,
        );
      }
      window.setTimeout(() => map.invalidateSize(), 0);
    } else if (status) {
      status.textContent =
        "La carte n’a pas pu être chargée. La recherche de lieu reste disponible.";
    }

    search?.addEventListener("input", () => {
      window.clearTimeout(searchTimer);
      const query = search.value.trim();
      searchTimer = window.setTimeout(() => searchPlaces(query), 450);
    });
    search?.addEventListener("keydown", (event) => {
      if (event.key === "Escape") hideResults();
    });

    trigger?.addEventListener("click", () => {
      if (!navigator.geolocation) {
        if (status)
          status.textContent =
            "La géolocalisation n’est pas disponible dans ce navigateur.";
        return;
      }

      trigger.disabled = true;
      if (status) status.textContent = "Localisation en cours…";

      navigator.geolocation.getCurrentPosition(
        (position) => {
          setPosition(position.coords.latitude, position.coords.longitude);
          reversePosition(position.coords.latitude, position.coords.longitude);
          trigger.disabled = false;
        },
        () => {
          if (status)
            status.textContent =
              "Position indisponible. Vérifiez l’autorisation du navigateur.";
          trigger.disabled = false;
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
      );
    });
  });
});
