(function($, wpApiListSettings, Foundation) {
  "use strict";

  function getCookie(cname) {
    var name = cname + "=";
    var decodedCookie = decodeURIComponent(document.cookie);
    var ca = decodedCookie.split(';');
    for(var i = 0; i <ca.length; i++) {
      var c = ca[i];
      while (c.charAt(0) == ' ') {
        c = c.substring(1);
      }
      if (c.indexOf(name) == 0) {
        return c.substring(name.length, c.length);
      }
    }
    return "";
  }
  let currentFilter = {}
  let items = []
  let customFilters = []
  let savedFilters = wpApiListSettings.filters || {[wpApiListSettings.current_post_type]:[]}
  if (Array.isArray(savedFilters)){
    savedFilters = {}
  }
  if ( !savedFilters[wpApiListSettings.current_post_type]){
    savedFilters[wpApiListSettings.current_post_type] = []
  }
  let filterToSave = ""
  let filterToDelete = ""
  let currentFilters = $("#current-filters")
  let newFilterLabels = []
  let typeaheadTotals = {}
  let loading_spinner = $(".loading-spinner")
  let tableHeaderRow = $('.js-list thead .sortable th')
  let getContactsPromise = null


  function get_contacts( offset = 0, sort ) {
    loading_spinner.addClass("active")
    let data = currentFilter.query
    document.cookie = `last_view=${JSON.stringify(currentFilter)}`

    if ( offset ){
      data.offset = offset
    }
    if ( sort ){
      data.sort = sort
      data.offset = 0
    } else {
      data.sort = wpApiListSettings.current_post_type === "contacts" ? "overall_status" : "status";
    }
    //abort previous promise if it is not finished.
    if (getContactsPromise && _.get(getContactsPromise, "readyState") !== 4){
      getContactsPromise.abort()
    }
    getContactsPromise = $.ajax({
      url: wpApiListSettings.root + "dt/v1/" + wpApiListSettings.current_post_type + "/search",
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', wpApiListSettings.nonce);
      },
      data: data,
    })
    getContactsPromise.then((data)=>{
      if (offset){
        items = _.unionBy(items, data[wpApiListSettings.current_post_type] || [], "ID")
      } else  {
        items = data[wpApiListSettings.current_post_type] || []
      }
      let result_text = wpApiListSettings.translations.txt_info.replace("_START_", items.length).replace("_TOTAL_", data.total)
      $('.filter-result-text').html(result_text)
      $("#current-filters").html(selectedFilters.html())
      displayRows();
      setupCurrentFilterLabels()
      loading_spinner.removeClass("active")
    })
  }

  let savedFiltersList = $("#saved-filters")

  function setupFilters(filters){
    savedFiltersList.empty()
    filters.forEach(filter=>{
      if (filter){
        let deleteFilter = $(`<span style="float:right" data-filter="${filter.ID}">
            ${wpApiListSettings.translations.delete}
        </span>`).on("click", function () {
          $(`.delete-filter-name`).html(filter.name)
          $('#delete-filter-modal').foundation('open');
          filterToDelete = filter.ID;
        })
        const radio = $(`<input name='view' class='js-list-view' autocomplete='off' data-id="${filter.ID}" >`)
          .attr("type", "radio")
          .val("saved-filters")
          .on("change", function() {
          });
        savedFiltersList.append(
          $("<div>").append(
            $("<label>")
              .css("cursor", "pointer")
              .addClass("js-filter-checkbox-label")
              // .data("filter-type", filterType)
              .data("filter-value", status)
              .append(radio)
              .append(document.createTextNode(filter.name))
              .append(deleteFilter)

          )
        )
      }
    })
  }

  setupFilters(savedFilters[wpApiListSettings.current_post_type])
  //look at the cookie to see what was the last selected view
  let cachedFilter = JSON.parse(getCookie("last_view")||"{}")
  if ( cachedFilter && !_.isEmpty(cachedFilter)){
    if (cachedFilter.type==="saved-filters"){
      if ( _.find(savedFilters[wpApiListSettings.current_post_type], {ID: cachedFilter.ID})){
        $(`input[name=view][value=saved-filters][data-id='${cachedFilter.ID}']`).prop('checked', true);
      }
    } else if ( cachedFilter.type==="default" ){
      $("input[name=view][value=" + cachedFilter.ID + "]").prop('checked', true);
    } else if ( cachedFilter.type === "custom_filter" ){
      addCustomFilter(cachedFilter.name, "default", cachedFilter.query, cachedFilter.labels)
    }
  }


  $(function() {
    $(window).resize(function() {
      if (Foundation.MediaQuery.is('small only')) {
        if ($(".js-filters-accordion .js-filters-modal-content").length === 0) {
          $(".js-filters-accordion").append($(".js-filters-modal-content").detach());
        }
      } else {
        if ($(".js-pane-filters .js-filters-modal-contact").length === 0) {
          $(".js-pane-filters").append($(".js-filters-modal-content").detach());
        }
      }
    }).trigger("resize");
  });

  const templates = {
    contacts: _.template(`<tr>
      <!--<td><img src="<%- template_directory_uri %>/dt-assets/images/star.svg" width=13 height=12></td>-->
      <!--<td></td>-->
      <td>
        <a href="<%- permalink %>"><%- post_title %></a>
        <br>
        <%- phone_numbers.join(", ") %>
        <span class="show-for-small-only">
            <span class="milestone milestone--<%- sharing_milestone_key %>"><%- sharing_milestone %></span>
            <span class="milestone milestone--<%- belief_milestone_key %>"><%- belief_milestone %></span>
            <%- status %>
            <!--<%- assigned_to ? assigned_to.name : "" %>-->
            <%= locations.join(", ") %>
            <%= group_links %>
          </span>
      </td>
      <td class="hide-for-small-only"><span class="status status--<%- overall_status %>"><%- status %></span></td>
      <td class="hide-for-small-only"><span class="status status--<%- seeker_path %>"><%- seeker_path %></span></td>
      <td class="hide-for-small-only">
        <span class="milestone milestone--<%- sharing_milestone_key %>"><%- sharing_milestone %></span>
        <br>
        <span class="milestone milestone--<%- belief_milestone_key %>"><%- belief_milestone %></span>
      </td>
      <td class="hide-for-small-only"><%- assigned_to ? assigned_to.name : "" %></td>
      <td class="hide-for-small-only"><%= locations.join(", ") %></td>
      <td class="hide-for-small-only"><%= group_links %></td>
      <!--<td><%- last_modified %></td>-->
    </tr>`),
    groups: _.template(`<tr>
      <!--<td><img src="<%- template_directory_uri %>/dt-assets/images/green_flag.svg" width=10 height=12></td>-->
      <!--<td></td>-->
      <td class="show-for-small-only">
        <a href="<%- permalink %>"><%- post_title %></a>
        <br>
        <%- status %> <%- type %> <%- member_count %> 
        <%- locations.join(", ") %> 
        <%= leader_links %> 
      </td>
      <td class="hide-for-small-only"><a href="<%- permalink %>"><%- post_title %></a></td>
      <td class="hide-for-small-only"><span class="group-status group-status--<%- group_status %>"><%- status %></span></td>
      <td class="hide-for-small-only"><span class="group-type group-type--<%- group_type %>"><%- type %></span></td>
      <td class="hide-for-small-only" style="text-align: right"><%- member_count %></td>
      <td class="hide-for-small-only"><%= leader_links %></td>
      <td class="hide-for-small-only"><%- locations.join(", ") %></td>
      <!--<td><%- last_modified %></td>-->
    </tr>`),
  };

  function displayRows() {
    const $table = $(".js-list");
    if (!$table.length) {
      return;
    }
    $table.find("> tbody").empty();
    let rows = ""
    _.forEach(items, function (item, index) {
      if (wpApiListSettings.current_post_type === "contacts") {
        rows += buildContactRow(item, index)[0].outerHTML;
      } else if (wpApiListSettings.current_post_type === "groups") {
        rows += buildGroupRow(item, index)[0].outerHTML
      }
    });
    $table.append(rows)
  }

  function buildContactRow(contact, index) {
    const template = templates[wpApiListSettings.current_post_type];
    const ccfs = wpApiListSettings.custom_fields_settings;
    const belief_milestone_key = _.find(
      ['baptizing', 'baptized', 'belief'],
      function(key) { return contact["milestone_" + key]; }
    );
    const sharing_milestone_key = _.find(
      ['planting', 'in_group', 'sharing', 'can_share'],
      function(key) { return contact["milestone_" + key]; }
    );
    let status = ccfs.overall_status.default[contact.overall_status];
    let seeker_path = ccfs.seeker_path.default[contact.seeker_path];
    // if (contact.overall_status === "active") {
    //   status = ccfs.seeker_path.default[contact.seeker_path];
    // } else {
    //   status = ccfs.overall_status.default[contact.overall_status];
    // }
    const group_links = _.map(contact.groups, function(group) {
      return '<a href="' + _.escape(group.permalink) + '">' + group.post_title + "</a>";
    }).join(", ");
    const context = _.assign({last_modified: 0}, contact, wpApiListSettings, {
      index,
      status,
      belief_milestone_key,
      sharing_milestone_key,
      seeker_path,
      belief_milestone: (ccfs["milestone_" + belief_milestone_key] || {}).name || "",
      sharing_milestone: (ccfs["milestone_" + sharing_milestone_key] || {}).name || "",
      group_links,
    });
    return $.parseHTML(template(context));
  }

  function buildGroupRow(group, index) {
    const template = templates[wpApiListSettings.current_post_type];
    const leader_links = _.map(group.leaders, function(leader) {
      return '<a href="' + _.escape(leader.permalink) + '">' + _.escape(leader.post_title) + "</a>";
    }).join(", ");
    const gcfs = wpApiListSettings.custom_fields_settings;
    const status = gcfs.group_status.default[group.group_status || "active"];
    const type = gcfs.group_type.default[group.group_type || "active"];
    const context = _.assign({}, group, wpApiListSettings, {
      leader_links,
      status,
      type
    });
    return $.parseHTML(template(context));
  }

  $(document).on('change', '.js-list-view', e => {
    getContactForCurrentView()
  });


  function setupCurrentFilterLabels() {
    let html = ""
    let filter = currentFilter
    if (filter && filter.labels){
      filter.labels.forEach(label=>{
        html+= `<span class="current-filter ${label.field}" id="${label.id}">${label.name}</span>`
      })
    } else {
      let query = filter.query
      for( let query_key in query ) {
        if (Array.isArray(query[query_key])) {

          query[query_key].forEach(q => {

            html += `<span class="current-filter ${query_key}" id="${q}">${q}</span>`
          })
        } else {
          html += `<span class="current-filter search" id="${query[query_key]}">${query[query_key]}</span>`
        }
      }
    }
    currentFilters.html(html)
  }

  function getContactForCurrentView() {
    let checked = $(".js-list-view:checked")
    let currentView = checked.val()
    //reset sorting in table header
    tableHeaderRow.removeClass("sorting_asc")
    tableHeaderRow.removeClass("sorting_desc")
    $('.js-list thead .sortable th[data-id="overall_status"]').addClass("sorting_asc")
    tableHeaderRow.data("sort", '')
    $('.js-list thead .sortable th[data-id="overall_status"]').data("sort", 'asc')

    let query = {assigned_to:["me"]}
    let filter = {type:"default", ID:currentView, query:{}, labels:[{ id:"me", name:"My Contacts", field: "assigned"}]}
    if ( currentView === "all" ){
      query.assigned_to = ["all"]
      filter.labels = [{ id:"all", name:"All", field: "assigned"}]
    } else if ( currentView === "shared_with_me" ){
      query.assigned_to = ["shared"]
      filter.labels = [{ id:"shared", name:"Shared with me", field: "assigned"}]
    }
    if ( currentView === "assignment_needed" ){
      query.overall_status = ["unassigned"]
      filter.labels = [{ id:"unassigned", name:"Assignment needed", field: "assigned"}]
    } else if ( currentView === "update_needed" ){
      filter.labels = [{ id:"update_needed", name:"Update needed", field: "requires_update"}]
      query.requires_update = ["yes"]
    } else if ( currentView === "meeting_scheduled" ){
      query.overall_status = ["active"]
      query.seeker_path = ["scheduled"]
      filter.labels = [{ id:"active", name:"Meeting scheduled", field: "seeker_path"}]
    } else if ( currentView === "contact_unattempted" ){
      query.overall_status = ["active"]
      query.seeker_path = ["none"]
      filter.labels = [{ id:"all", name:"Contact attempt needed", field: "seeker_path"}]
    } else if ( currentView === "custom_filter"){
      let filterId = checked.data("id")
      filter = _.find(customFilters, {ID:filterId})
      filter.type = currentView
      query = filter.query
    } else if ( currentView === "saved-filters" ){
      let filterId = checked.data("id")
      filter = _.find(savedFilters[wpApiListSettings.current_post_type], {ID:filterId})
      filter.type = currentView
      query = filter.query
    }
    filter.query = query

    currentFilter = JSON.parse(JSON.stringify(filter))

    get_contacts()
  }
  if (!getContactsPromise){
    getContactForCurrentView()
  }





  $('#filter-modal').on("open.zf.reveal", function () {
    newFilterLabels=[]
    if ( wpApiListSettings.current_post_type === "groups" ){
      loadLocationTypeahead()
      loadAssignedToTypeahead()
      loadLeadersTypeahead()
    } else if ( wpApiListSettings.current_post_type === "contacts" ){
      loadLocationTypeahead()
      loadAssignedToTypeahead()
      loadSubassignedTypeahead()
    }
    $("#filter-modal input:checked").each(function () {
      $(this).prop('checked', false)
    })
    selectedFilters.empty();
    $(".typeahead__query input").each(function () {
      let typeahead = Typeahead['.'+$(this).attr("class").split(/\s+/)[0]]
      for (let i = 0; i < typeahead.items.length; i ){
        typeahead.cancelMultiselectItem(0)
      }
      typeahead.node.trigger('propertychange.typeahead')
    })
  })

  $('.tabs-title a').on("click", function () {
    let id = $(this).attr('href').replace('#', '')
    $(`.js-typeahead-${id}`).trigger('input')
  })

  //create new filter
  let selectedFilters = $("#selected-filters")
  $("#confirm-filter-contacts").on("click", function () {

    let searchQuery = {}
    searchQuery.assigned_to = _.map(_.get(Typeahead['.js-typeahead-assigned_to'], "items"), "ID")
    searchQuery.locations = _.map(_.get(Typeahead['.js-typeahead-locations'], "items"), "ID")
    let fields = []
    if (wpApiListSettings.current_post_type === "groups"){
      searchQuery.leaders = _.map(_.get(Typeahead['.js-typeahead-leaders'], "items"), "ID")
      fields = ["group_type", "group_status"]
    } else if ( wpApiListSettings.current_post_type === "contacts" ){
      searchQuery.subassigned = _.map(_.get(Typeahead['.js-typeahead-subassigned'], "items"), "ID")
      fields = ["overall_status", "seeker_path", "requires_update"]
      $("#faith_milestones-options input:checked").each(function(){
        searchQuery[$(this).val()] = ["yes"]
      })
    }

    //get checked field options
    fields.forEach(field=>{
      searchQuery[field] =[]
      $(`#${field}-options input:checked`).each(function(){
        searchQuery[field].push($(this).val())
      })
    })

    addCustomFilter("Custom Filter", "custom-filter", searchQuery, newFilterLabels)
  })

  function addCustomFilter(name, type, query, labels) {
    query = query || currentFilter.query
    let ID = new Date().getTime() / 1000

    currentFilter = {ID, type, name, query:JSON.parse(JSON.stringify(query)), labels:labels}
    customFilters.push(JSON.parse(JSON.stringify(currentFilter)))

    let saveFilter = $(`<span style="float:right" data-filter="${ID}">
        ${wpApiListSettings.translations.save}
    </span>`).on("click", function () {
      $('#save-filter-modal').foundation('open');
      filterToSave = ID;
    })
    let filterRow = $(`<label class='list-view ${ID}'>`).append(`
      <input type="radio" name="view" value="custom_filter" data-id="${ID}" class="js-list-view" checked autocomplete="off">
        ${name}
    `).append(saveFilter)
    $(".custom-filters").append(filterRow)
    $(".custom-filters input").on("change", function () {
      getContactForCurrentView()
    })
    getContactForCurrentView()
  }

  $(`#confirm-filter-save`).on('click', function () {
    let filterName = $('#filter-name').val()

    let filter = _.find(customFilters, {ID:filterToSave})
    if (filter.query){
      let newFilter = {
        name: filterName,
        ID: filterToSave,
        query:filter.query,
        labels: filter.labels
      };

      savedFilters[wpApiListSettings.current_post_type].push(newFilter)
      API.save_filters(savedFilters).then(()=>{
        $(`.custom-filters [class*="list-view ${filterToSave}`).remove()
        setupFilters(savedFilters[wpApiListSettings.current_post_type])
        $(`input[name="view"][value="saved-filters"][data-id='${filterToSave}']`).prop('checked', true);
        getContactForCurrentView()
      })
    }
  })

  $(`#confirm-filter-delete`).on('click', function () {
    _.pullAllBy(savedFilters[wpApiListSettings.current_post_type], [{ID:filterToDelete}], "ID")
    API.save_filters(savedFilters).then(()=>{
      setupFilters(savedFilters[wpApiListSettings.current_post_type])
    })
  })


  $("#search").on("click", function () {
    let searchText = $("#search-query").val()
    let query = {text:searchText, assigned_to:["all"]}
    let labels = [{ id:"search", name:searchText, field: "search"}]
    addCustomFilter(searchText, "search", query, labels)
  })
  $("#search-mobile").on("click", function () {
    let searchText = $("#search-query-mobile").val()
    let query = {text:searchText, assigned_to:["all"]}
    let labels = [{ id:"search", name:searchText, field: "search"}]
    addCustomFilter(searchText, "search", query, labels)
  })
  $('.search-input').on('keyup', function (e) {
    if ( e.keyCode === 13 ){
      $("#search").trigger("click")
    }
  })
  $('.search-input-mobile').on('keyup', function (e) {
    if ( e.keyCode === 13 ){
      $("#search-mobile").trigger("click")
    }
  })


  $(".js-list-filter-title").on("click", function() {
    const $title = $(this);
    $title.parents(".js-list-filter").toggleClass("filter--closed");
  }).on("keydown", function(event) {
    if (event.keyCode === 13) {
      $(this).trigger("click");
    }
  });

  //toggle show search input on mobile
  $("#open-search").on("click", function () {
    $(".hideable-search").toggle()
  })

  $("#load-more").on('click', function () {
    get_contacts( items.length )
  })

  $('.js-list th').click(function () {
    let id = $(this).data('id')
    let sort = $(this).data('sort')
    tableHeaderRow.removeClass("sorting_asc")
    tableHeaderRow.removeClass("sorting_desc")
    tableHeaderRow.data("sort", '')
    if ( !sort || sort === 'desc' ){
      $(this).data('sort', 'asc')
      $(this).addClass("sorting_asc")
      $(this).removeClass("sorting_desc")
    } else {
      $(this).data('sort', 'desc')
      $(this).removeClass("sorting_asc")
      $(this).addClass("sorting_desc")
      id = `-${id}`
    }
    get_contacts(0, id)
  })

  $('.js-sort-by').click(function () {
    tableHeaderRow.removeClass("sorting_asc")
    tableHeaderRow.removeClass("sorting_desc")
    let dir = $(this).data('order')
    let field = $(this).data('field')
    get_contacts(0, (dir === "asc" ? "" : '-') + field)
  })


  /**
   * Modal options
   */

  /**
   * Locations
   */
  let loadLocationTypeahead = ()=> {
    if (!window.Typeahead['.js-typeahead-locations']) {
      $.typeahead({
        input: '.js-typeahead-locations',
        minLength: 0,
        accent: true,
        searchOnFocus: true,
        maxItem: 20,
        template: function (query, item) {
          return `<span>${_.escape(item.name)}</span>`
        },
        source: TYPEAHEADS.typeaheadSource('locations', 'dt/v1/locations-compact/'),
        display: "name",
        templateValue: "{{name}}",
        dynamic: true,
        multiselect: {
          matchOn: ["ID"],
          data: [],
          callback: {
            onCancel: function (node, item) {
              $(`#${item.ID}.locations`).remove()
              _.pullAllBy(newFilterLabels, [{id: item.ID}], "id")
            }
          }
        },
        callback: {
          onResult: function (node, query, result, resultCount) {
            let text = TYPEAHEADS.typeaheadHelpText(resultCount, query, result)
            $('#locations-result-container').html(text);
          },
          onHideLayout: function () {
            $('#locations-result-container').html("");
          },
          onClick: function (node, a, item, event) {
            newFilterLabels.push({id: item.ID, name: item.name, field: "locations"})
            selectedFilters.append(`<span class="current-filter locations" id="${item.ID}">${item.name}</span>`)
          }
        }
      });
    }
  }

  /**
   * Leaders
   */
  let loadLeadersTypeahead = ()=> {
    if (!window.Typeahead['.js-typeahead-leaders']) {
      $.typeahead({
        input: '.js-typeahead-leaders',
        minLength: 0,
        accent: true,
        searchOnFocus: true,
        maxItem: 20,
        template: function (query, item) {
          return `<span>${_.escape(item.name)}</span>`
        },
        source: TYPEAHEADS.typeaheadSource('leaders', 'dt/v1/contacts/compact'),
        display: "name",
        templateValue: "{{name}}",
        dynamic: true,
        multiselect: {
          matchOn: ["ID"],
          data: [],
          callback: {
            onCancel: function (node, item) {
              $(`#${item.ID}.leaders`).remove()
              _.pullAllBy(newFilterLabels, [{id: item.ID}], "id")
            }
          }
        },
        callback: {
          onResult: function (node, query, result, resultCount) {
            let text = TYPEAHEADS.typeaheadHelpText(resultCount, query, result)
            $('#leaders-result-container').html(text);
          },
          onHideLayout: function () {
            $('#leaders-result-container').html("");
          },
          onClick: function (node, a, item, event) {
            newFilterLabels.push({id: item.ID, name: item.name, field: "leaders"})
            selectedFilters.append(`<span class="current-filter leaders" id="${item.ID}">${item.name}</span>`)
          }
        }
      });
    }
  }

  /**
   * Leaders
   */
  let loadSubassignedTypeahead = ()=> {
    if (!window.Typeahead['.js-typeahead-subassigned']) {
      $.typeahead({
        input: '.js-typeahead-subassigned',
        minLength: 0,
        accent: true,
        searchOnFocus: true,
        maxItem: 20,
        template: function (query, item) {
          return `<span>${_.escape(item.name)}</span>`
        },
        source: TYPEAHEADS.typeaheadSource('subassigned', 'dt/v1/contacts/compact'),
        display: "name",
        templateValue: "{{name}}",
        dynamic: true,
        multiselect: {
          matchOn: ["ID"],
          data: [],
          callback: {
            onCancel: function (node, item) {
              $(`#${item.ID}.subassigned`).remove()
              _.pullAllBy(newFilterLabels, [{id: item.ID}], "id")
            }
          }
        },
        callback: {
          onResult: function (node, query, result, resultCount) {
            let text = TYPEAHEADS.typeaheadHelpText(resultCount, query, result)
            $('#subassigned-result-container').html(text);
          },
          onHideLayout: function () {
            $('#subassigned-result-container').html("");
          },
          onClick: function (node, a, item, event) {
            newFilterLabels.push({id: item.ID, name: item.name, field: "subassigned"})
            selectedFilters.append(`<span class="current-filter subassigned" id="${item.ID}">${item.name}</span>`)
          }
        }
      });
    }
  }

  /**
   * Assigned_to
   */
  let loadAssignedToTypeahead = ()=>{
    if ( !window.Typeahead[".js-typeahead-assigned_to"]){
      $.typeahead({
        input: '.js-typeahead-assigned_to',
        minLength: 0,
        accent: true,
        searchOnFocus: true,
        multiselect: {
          matchOn: ["ID"],
          data: [],
          callback: {
            onCancel: function (node, item) {
              $(`#${item.ID}.assigned_to`).remove()
              _.pullAllBy(newFilterLabels, [{id:item.ID}], "id")
            }
          }
        },
        source: {
          users: {
            display: ["name", "user"],
            ajax: {
              url: wpApiListSettings.root + 'dt/v1/users/get_users',
              data: {
                s: "{{query}}"
              },
              beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpApiListSettings.nonce);
              },
            }
          }
        },

        templateValue: "{{name}}",
        template: function (query, item) {
          return `<span class="row">
            <span class="avatar"><img src="{{avatar}}"/> </span>
            <span>${item.name}</span>
          </span>`
        },
        dynamic: true,
        hint: true,
        emptyTemplate: 'No users found "{{query}}"',
        callback: {
          onResult: function (node, query, result, resultCount) {
            let text = TYPEAHEADS.typeaheadHelpText(resultCount, query, result)
            $('#assigned_to-result-container').html(text);
          },
          onClick: function(node, a, item, event) {
            selectedFilters.append(`<span class="current-filter assigned_to" id="${item.ID}">${item.name}</span>`)
            newFilterLabels.push({id:item.ID, name:item.name, field:"assigned_to"})

          }
        }
      });
    }
  }


  /**
   * checkboxes
   */
  let fields = []
  if ( wpApiListSettings.current_post_type === "groups" ){
    fields = ["group_type", "group_status"]
  } else if ( wpApiListSettings.current_post_type === "contacts" ){
    fields = ["overall_status", "seeker_path", "requires_update"]
  }
  fields.forEach(field_key=>{
    let field_options = _.get(wpApiListSettings, `custom_fields_settings.${field_key}.default`) || {}
    for( let status in  field_options ){
      const checkbox = $("<input autocomplete='off'>")
        .attr("type", "checkbox")
        .val(status)
        .on("change", function(a, b, c) {
          if ($(this).is(":checked")){
            let optionId = $(this).val()
            let optionName = field_options[optionId]
            newFilterLabels.push({id:$(this).val(), name:optionName, field:field_key})
            selectedFilters.append(`<span class="current-filter ${field_key}" id="${optionId}">${optionName}</span>`)
          } else {
            $(`#${$(this).val()}.${field_key}`).remove()
            _.pullAllBy(newFilterLabels, [{id:optionId}], "id")
          }
        });
      $(`#${field_key}-options`).append(
        $("<div>").append(
          $("<label>")
            .css("cursor", "pointer")
            .data("filter-value", status)
            .append(checkbox)
            .append(document.createTextNode(field_options[status]))
        )
      );
    }
  })
  $(".milestone-filter").on("click", function () {
    let field = $(this).val()
    let name = _.get(wpApiListSettings, `custom_fields_settings.${field}.name`) || ""
    if ($(this).is(":checked")){
      newFilterLabels.push({id:field, name:name, field:"faith_milestones"})
      selectedFilters.append(`<span class="current-filter faith_milestones" id="${field}">${name}</span>`)
    } else {
      $(`#${field}.faith_milestones`).remove()
      _.pullAllBy(newFilterLabels, [{id:field}], "id")
    }
  })

})(window.jQuery, window.wpApiListSettings, window.Foundation);
