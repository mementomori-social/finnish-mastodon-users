// Fetch all users from single JSON file
async function getAllUsers() {
  const response = await fetch('cache/all-users.json', { cache: "no-cache" });
  if (!response.ok) throw new Error(`HTTP ${response.status}`);
  return response.json();
}

// DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {

  // If we have index.html in URL, remove it
  if (window.location.href.includes("index.html")) {
    window.history.replaceState({}, document.title, "/" + window.location.pathname.split("/")[1] + "/");
  }

  // Update aria-busy for user-count
  const userCountEl = document.getElementById('user-count');
  userCountEl.setAttribute('aria-busy', 'true');

  // Update aria-busy for user-list
  const userListEl = document.getElementById('user-list');
  userListEl.setAttribute('aria-busy', 'true');

  document.getElementById("user-list").innerHTML = "";

  // Load all users from single JSON file
  getAllUsers()
    .then(users => {
      let counter = 0;

      users.forEach(json => {
        try {
          let user = json._csv_key || json.acct;
          let acct = json.acct;
          let username = json.username;
          let user_instance = user.includes('@') ? user.split('@')[1] : json.url.split('/')[2];
          let display_name = json.display_name || acct;
          let bio = json.note || '';

          // Parse twemoji
          try {
            display_name = twemoji.parse(display_name, {
              className: "emojione",
              folder: '72x72',
              ext: '.png',
              base: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@latest/assets/'
            });
          } catch (e) {}

          try {
            bio = twemoji.parse(bio, {
              className: "emojione",
              folder: '72x72',
              ext: '.png',
              base: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@latest/assets/'
            });
          } catch (e) {}

          // If display name is empty, use username
          if (display_name === "") {
            display_name = acct;
          }

          // Get access_token from local storage
          let access_token = localStorage.getItem('finnish_mastodon_users_access_token');

          // Default to the original instance
          let instance_link = `https://${user_instance}/@${username}`;

          // Exception for Vivaldi
          if (user_instance === "vivaldi.net") {
            instance_link = `https://social.vivaldi.net/@${username}`;
          }

          // Follow button for logged out users
          let followButton = `<a id="button-action-${json.id}" href="${instance_link}" class="button button-action" aria-label="Mene käyttäjän ${acct} profiiliin">Profiili</a>`;

          // If we have access_token, let's have the authenticated user's instance for easier following
          if (access_token) {
            let authed_user_instance = localStorage.getItem('finnish_mastodon_user_authed_instance_url');
            instance_link = `${authed_user_instance}/@${user}`;
            followButton = `<button id="button-action-${json.id}" class="button button-action has-no-action" aria-label="Seuraa käyttäjää ${acct}, avautuu uuteen ikkunaan" data-follow-id="${json.id}" data-url="${instance_link}">Seuraa</button>`;
          }

          // Custom emoji replacement
          try {
            if (json.emojis && json.emojis.length > 0) {
              json.emojis.forEach(dp_emoji => {
                display_name = display_name.replaceAll(`:${dp_emoji.shortcode}:`, `<img src="${dp_emoji.url}" alt="Emoji ${dp_emoji.shortcode}" class="emojione">`);
                bio = bio.replaceAll(`:${dp_emoji.shortcode}:`, `<img src="${dp_emoji.url}" alt="Emoji ${dp_emoji.shortcode}" class="emojione">`);
              });
            }
          } catch (e) {}

          // User template
          let userTemplate = `
          <li class="account-card collapsed" id="user-${json.id}" data-user-name="${acct}" data-user-id="${json.id}" data-user-instance="${user_instance}">
            <button id="button-collapse-${json.id}" class="button-collapse account-card__permalink" aria-label="Näytä käyttäjän ${acct} lisätiedot" aria-expanded="false" aria-controls="user-${json.id}">
              <span class="screen-reader-text">Näytä lisätiedot</span>
            </button>
            <div class="account-card__header" aria-hidden="true">
              <img src="${json.header}" alt="Käyttäjän ${acct} header-kuva">
            </div>
            <div class="account-card__title">
              <div class="account-card__title__avatar">
                <div class="account__avatar" style="width: 56px; height: 56px;">
                  <img src="${json.avatar}" alt="Käyttäjän ${acct} profiilikuva">
                </div>
              </div>
              <span class="display-name">
                <bdi>
                  <strong class="display-name__html">${display_name}</strong>
                </bdi>
                <span class="display-name__account">@${user}</span>
              </span>
            </div>
            <div class="account-card__bio">
              ${bio}
              <p><a href="${instance_link}">Siirry profiilisivulle</a></p>
            </div>
            <div class="account-card__actions">
              <div class="account-card__counters">
                <div class="account-card__counters__item">
                  <span>${json.statuses_count}</span>
                  <small><span>Viestit</span></small>
                </div>
                <div class="account-card__counters__item">
                  <span>${json.following_count}</span>
                  <small><span>Seuraajat</span></small>
                </div>
                <div class="account-card__counters__item">
                  <span>${json.followers_count}</span>
                  <small><span>Seurataan</span></small>
                </div>
              </div>
              <div class="account-card__actions__button" id="actions__button-${json.id}">
                ${followButton}
              </div>
            </div>
          </li>`;

          // Append to DOM
          let userTemplateNode = document.createRange().createContextualFragment(userTemplate);
          userListEl.append(userTemplateNode);

          counter++;
        } catch (e) {
          console.error('Error processing user:', json.acct, e);
        }
      });

      // Update counter
      userCountEl.innerHTML = counter;
      userCountEl.setAttribute('aria-busy', 'false');
      userListEl.setAttribute('aria-busy', 'false');

      // Hide skeleton
      document.getElementById('skeleton').style.display = 'none';

      // Save to localStorage
      localStorage.setItem('finnish_mastodon_users_count', counter);
      localStorage.setItem('finnish_mastodon_users_timestamp', Date.now());

      // Save user list to localStorage
      let listedUsers = users.map(u => ({
        id: u.id,
        acct: u.acct,
        instance: u._csv_key ? u._csv_key.split('@')[1] : u.url.split('/')[2]
      }));
      localStorage.setItem('finnish_mastodon_users', JSON.stringify(listedUsers));
    })
    .catch(error => {
      console.error('Failed to load users:', error);
      userListEl.setAttribute('aria-busy', 'false');
      document.getElementById('heading-users').innerHTML = 'Virhe ladattaessa käyttäjiä. Yritä myöhemmin uudelleen.';
    });

  // Expand/Collapse single account-card based on user id
  function expandCollapseUser(id) {
    let user = document.querySelector("[data-user-id='"+id+"']");
    let button = document.getElementById("button-collapse-"+id);

    if (user === null) {
      return;
    }

    if (user.classList.contains("collapsed")) {
      user.classList.remove("collapsed");
      button.getElementsByTagName("span")[0].innerHTML = "Piilota lisätiedot";
      button.setAttribute("aria-expanded", "false");
    } else {
      user.classList.add("collapsed");
      button.getElementsByTagName("span")[0].innerHTML = "Näytä lisätiedot";
      button.setAttribute("aria-expanded", "true");
    }
  }

  // Run expandCollapseUser() on click
  document.addEventListener("click", function(e) {
    if (e.target && e.target.id.includes("button-collapse-")) {
      let id = e.target.id.split("button-collapse-")[1];
      expandCollapseUser(id);
    }
  });

  function showAllUsers() {
    let users = document.getElementsByClassName("account-card");
    for (let i = 0; i < users.length; i++) {
      users[i].style.display = "block";
    }
  }

  // Filter with search input
  document.getElementById("search").addEventListener("keyup", function() {
    let search = document.getElementById("search").value.toLowerCase();
    let users = document.getElementsByClassName("account-card");

    for (let i = 0; i < users.length; i++) {
      let account_username = users[i].getElementsByClassName("display-name__account")[0].innerHTML.toLowerCase();
      let account_display_name = users[i].getElementsByClassName("display-name")[0].innerHTML.toLowerCase();
      let account_bio = users[i].getElementsByClassName("account-card__bio")[0].innerHTML.toLowerCase();

      if (account_bio.includes(search) || account_display_name.includes(search) || account_username.includes(search)) {
        users[i].style.display = "block";
      } else {
        users[i].style.display = "none";
      }
    }
  });

  // Show all users when search cancel button is clicked
  document.getElementById("search").addEventListener("search", function(event) {
    if (event.target.value === "") {
      showAllUsers();
    }
  });
});
