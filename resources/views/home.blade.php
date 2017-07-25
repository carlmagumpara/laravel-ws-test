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
</div>

<div class="modal fade" id="calleeModal" data-controls-modal="calleeModal"  data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-body">
            <h3>
                <span id="user-caller"></span> is calling...
            </h3>
          <div class="center call-buttons">
            <button type="button" class="btn btn-success accept-button" data-dismiss="modal">Accept</button>
            <button type="button" class="btn btn-danger reject-button" data-dismiss="modal">Reject</button>
          </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="callerModal" data-controls-modal="callerModal"  data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-body">
          <h3>
              Calling <span id="user-callee"></span>...
          </h3>
          <div class="center call-buttons">
            <button type="button" class="btn btn-success cancel-button" data-dismiss="modal">Cancel</button>
          </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="defaultModal" data-controls-modal="defaultModal"  data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-body">
            <h3 id="messageInfo">
            </h3>
          <div class="center call-buttons">
            <button type="button" class="btn btn-info" data-dismiss="modal">
                Ok
            </button>
          </div>
      </div>
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
  var connection = new WebSocket('ws://localhost:3000')
  var callTimeout;

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
        $('#ringtoneSignal')[0].play()
        $('#calleeModal').modal('show')
        $('#defaultModal').modal('hide')
        $('#user-caller').text(json.caller_name)
        $('.accept-button').attr('caller-id', json.caller_id).attr('caller-name', json.caller_name)
        $('.reject-button').attr('caller-id', json.caller_id)
        callTimeout =  setTimeout(function(){
          var data = {
            type: 'not-answered',
            caller_id: json.caller_id,
            caller_name: json.caller_name,
            callee_id: '{{ Auth::user()->id }}',
            callee_name: '{{ Auth::user()->name }}'
          }
          connection.send(JSON.stringify(data))
        }, 10000)
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
        console.log(json)
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
        $('#ringtoneSignal')[0].pause()
        $('#calleeModal').modal('hide')
        $('#defaultModal').modal('show')
        $('#messageInfo').text(json.message)
        break;
      case 'cancelled':
        clearTimeout(callTimeout)
        $('#ringtoneSignal')[0].pause()
        $('#calleeModal').modal('hide')
        $('#defaultModal').modal('show')
        $('#messageInfo').text(json.message)
        break;
      default:
        console.log('[Frontend]: Opss... Something\'s wrong here.')
    }
  }

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
    $('#ringtoneSignal')[0].pause()
    clearTimeout(callTimeout)
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
    $('#ringtoneSignal')[0].pause()
    clearTimeout(callTimeout)
    var caller_id = $(this).attr('caller-id')
    var data = {
      type: 'rejected',
      caller_id: caller_id,
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
      caller_name: '{{ Auth::user()->name }}'
    }
    connection.send(JSON.stringify(data)) 
  })

})

</script>
@endsection
