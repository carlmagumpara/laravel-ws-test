@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-4">
            <div class="panel">
                <ul class="list-group">
                    @foreach($users as $user)
                        @if (Auth::user()->id != $user->id)
                            <li class="list-group-item">
                                <div class="row">
                                    <div class="col-md-9 col-xs-9">
                                        <div class="status" id="user-{{ $user->id }}"></div>
                                        {{ $user->name }}    
                                    </div>
                                    <div class="col-md-3 col-xs-3">
                                    <button class="btn btn-xs btn-block call-button" callee-id="{{ $user->id }}" callee-name="{{ $user->name }}">CALL</button>
                                    <button class="btn btn-xs btn-block chat-button" chatee-id="{{ $user->id }}" chatee-name="{{ $user->name }}">CHAT</button>
                                    </div>
                                </div>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="col-md-8">
            <div class="panel panel-default">
                <div class="panel-heading">Dashboard</div>
                <div class="panel-body">
                    <h2>Hi! {{ Auth::user()->name }}</h2>
                </div>
            </div>
        </div>
    </div>

  <div class="modal fade" id="calleeModal" data-controls-modal="calleeModal"  data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-sm" role="document">
      <div class="modal-content">
        <div class="modal-body">
              <h4>
                  <span id="user-caller"></span> is calling...
              </h4>
            <div class="center call-buttons">
              <button type="button" class="btn btn-success accept-button btn-sm" data-dismiss="modal">Accept</button>
              <button type="button" class="btn btn-danger reject-button btn-sm" data-dismiss="modal">Reject</button>
            </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="callerModal" data-controls-modal="callerModal"  data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-sm" role="document">
      <div class="modal-content">
        <div class="modal-body">
            <h4>
                Calling <span id="user-callee"></span>...
            </h4>
            <div class="center call-buttons">
              <button type="button" class="btn btn-success cancel-button btn-sm" data-dismiss="modal">Cancel</button>
            </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="defaultModal" data-controls-modal="defaultModal"  data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-sm" role="document">
      <div class="modal-content">
        <div class="modal-body">
              <h5 id="messageInfo"></h5>
            <div class="center call-buttons">
              <button type="button" class="btn btn-info btn-sm" data-dismiss="modal">
                  Ok
              </button>
            </div>
        </div>
      </div>
    </div>
  </div>


  <div class="panel panel-danger" id="chat-container">
    <div class="panel-heading">
      Chat with <span id="chatee-name"></span>
    </div>
    <div class="panel-body">
      <div class="chat-body">

      </div>
    </div>
    <div class="panel-footer">
      <form id="chat-message">
        <input type="hidden" class="form-control" name="chatee-name">
        <input type="hidden" class="form-control" name="chatee-id">
        <input type="text" class="form-control" name="message">
      </form>
    </div>
  </div>


</div>

<audio id="callingSignal" loop>
  <source src="{{ asset('audio/calling.ogg') }}"></source>
  <source src="{{ asset('audio/calling.mp3') }}"></source>
</audio>

<audio id="ringtoneSignal" loop>
  <source src="{{ asset('audio/ringtone.ogg') }}"></source>
  <source src="{{ asset('audio/ringtone.mp3') }}"></source>
</audio>
<script type="text/javascript">

$(document).ready(function(){

  window.WebSocket = window.WebSocket || window.MozWebSocket
  // var connection = new WebSocket('ws://localhost:5000')
  var connection = new WebSocket('wss://ws-test-node.herokuapp.com')
  var callTimeout
  var title = $('title').text()

  if (!window.WebSocket) {
    console.log('Sorry, but your browser doesn\'t support WebSocket.')
  }

  connection.onopen = function () {
    var data = {
      type: 'subscribe',
      user_id: '{{ Auth::user()->id }}'
    }
    connection.send(JSON.stringify(data))
  }

  connection.onerror = function (error) {
    console.log('Sorry, but there\'s some problem with your connection or the server is down.')
  }

  connection.onmessage = function (message) {
    try {
      var json = JSON.parse(message.data)
    } catch (e) {
      console.log('This doesn\'t look like a valid JSON: ', message.data)
      return;
    }
    switch(json.type) {
      case 'subscribe':
        $('.status')
          .removeClass('online')
        for (var i = 0; i < json.data.length; i++) {
          $('#user-'+json.data[i])
            .addClass('online')
        }
        break;
      case 'calling':
        $('title').text(json.caller_name+' is calling...')
        $('#ringtoneSignal')[0].play()
        $('#calleeModal').modal('show')
        $('#defaultModal').modal('hide')
        $('#user-caller').text(json.caller_name)
        $('.accept-button').attr('caller-id', json.caller_id).attr('caller-name', json.caller_name)
        $('.reject-button').attr('caller-id', json.caller_id).attr('caller-name', json.caller_name)
        break;
      case 'ringing':
        $('#callingSignal')[0].play()
        $('#callerModal').modal('show')
        $('#user-callee').text(json.callee_name)
        $('.cancel-button').attr('callee-id', json.callee_id)
        break;
      case 'user-is-offline':
        $('#callerModal').modal('hide')
        $('#defaultModal').modal('show')
        $('#messageInfo').text(json.message)
        break;
      case 'accepted':
        $('#callingSignal')[0].pause()
        $('#callerModal').modal('hide')
        // console.log(json)
        console.log('Call Accepted')
        break;
      case 'rejected':
        $('#callingSignal')[0].pause()
        $('#callerModal').modal('hide')
        $('#defaultModal').modal('show')
        $('#messageInfo').text(json.message)
        break;
      case 'not-answered':
        $('#callingSignal')[0].pause()
        $('#callerModal').modal('hide')
        $('#defaultModal').modal('show')
        $('#messageInfo').text(json.message)
        break;
      case 'missed-call':
        $('title').text(title)
        $('#ringtoneSignal')[0].pause()
        $('#calleeModal').modal('hide')
        $('#defaultModal').modal('show')
        $('#messageInfo').text(json.message)
        break;
      case 'cancelled':
        $('title').text(title)
        $('#ringtoneSignal')[0].pause()
        $('#calleeModal').modal('hide')
        $('#defaultModal').modal('show')
        $('#messageInfo').text(json.message)
        break;
      case 'user-busy':
        $('#callerModal').modal('hide')
        $('#defaultModal').modal('show')
        $('#messageInfo').text(json.message)
        break;
      case 'message':
        var html;
        if (json.chatter_id == '{{ Auth::user()->id }}') {
          html = '<div class="row chat-item"> <div class="col-md-12 col-xs-12"> <h6>You</h6> <div class="chat-message"> <small>'+json.message+'</small> </div></div></div>'
          $('#chat-user-' + json.chatee_id).append(html)
          $('#chat-user-' + json.chatee_id).animate({ scrollTop: $(document).height() }, "slow");
        }
        if (json.chatee_id == '{{ Auth::user()->id }}') {
          html = '<div class="row chat-item"> <div class="col-md-2 col-xs-2"> <div class="avatar-container"> <img src="" class="img-responsive avatar"> </div></div><div class="col-md-10 col-xs-10"> <h6>'+json.chatter_name+'</h6> <div class="chat-message"> <small>'+json.message+'</small> </div></div></div>'
          $('#chat-user-' +json.chatter_id).append(html)
          $('#chat-user-' + json.chatter_id).animate({ scrollTop: $(document).height() }, "slow");
        }
        break
      default:
        console.log('[Frontend]: Opss... Something\'s wrong here.')
    }
  }

  $(window).on('beforeunload', function(){
    if($('#callerModal').hasClass('in')){
      $('.cancel-button').click();
    }
    if($('#calleeModal').hasClass('in')){
      $('.reject-button').click();
    }
  });

  $('.call-button').click(function(){
    var callee_id = $(this).attr('callee-id')
    var callee_name = $(this).attr('callee-name')
    var data = {
      type: 'calling',
      caller_id: '{{ Auth::user()->id }}',
      caller_name: '{{ Auth::user()->name }}',
      callee_id: callee_id,
      callee_name: callee_name
    }
    connection.send(JSON.stringify(data))
  })

  $('.accept-button').click(function(){
    $('title').text(title)
    $('#ringtoneSignal')[0].pause()
    var caller_id = $(this).attr('caller-id')
    var caller_name = $(this).attr('caller-name')
    var data = {
      type: 'accepted',
      caller_id: caller_id,
      caller_name: caller_name,
      callee_id: '{{ Auth::user()->id }}',
      callee_name: '{{ Auth::user()->name }}'
    }
    connection.send(JSON.stringify(data))
  })

  $('.reject-button').click(function(){
    $('title').text(title)
    $('#ringtoneSignal')[0].pause()
    var caller_id = $(this).attr('caller-id')
    var caller_name = $(this).attr('caller-name')
    var data = {
      type: 'rejected',
      caller_id: caller_id,
      callee_id: '{{ Auth::user()->id }}',
      callee_name: '{{ Auth::user()->name }}'
    }
    connection.send(JSON.stringify(data)) 
  })

  $('.cancel-button').click(function(){
    $('#callingSignal')[0].pause()
    var callee_id = $(this).attr('callee-id')
    var data = {
      type: 'cancelled',
      callee_id: callee_id,
      caller_id: '{{ Auth::user()->id }}',
      caller_name: '{{ Auth::user()->name }}'
    }
    connection.send(JSON.stringify(data)) 
  })


  $('#chat-container .panel-heading').click(function(){
    if ($('#chat-container').hasClass('hide-chat')) {
      $('#chat-container').removeClass('hide-chat');
    } else {
      $('#chat-container').addClass('hide-chat');
    }
  });


  // Chat

  $('.chat-button').click(function(){
    var chatee_id = $(this).attr('chatee-id')
    var chatee_name = $(this).attr('chatee-name')
    $('#chatee-name').html(chatee_name);
    $('[name=chatee-name]').val(chatee_name)
    $('[name=chatee-id]').val(chatee_id)
    $('.chat-body').attr('id','chat-user-' + chatee_id)
  });

  $('#chat-message').submit(function(e){
    if ($('[name=message]').val() != '') {
      if ($('[name=chatee-id]').val() != '') {
        var data = {
          type: 'message',
          chatee_id: $('[name=chatee-id]').val(),
          chatee_name: $('[name=chatee-name]').val(),
          chatter_id: '{{ Auth::user()->id }}',
          chatter_name: '{{ Auth::user()->name }}',
          message: $('[name=message]').val(),
          date: new Date().toLocaleString()
        }
        connection.send(JSON.stringify(data))  
        $('[name=message]').val('')   
      } else {
        alert('Please select user')
      }
    }

    e.preventDefault();
  })

})

</script>
@endsection
