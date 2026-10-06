using NAudio.Wave;

namespace ElvadoPress.App.Windows;

public sealed class NativePlayer : IDisposable
{
    private WaveOutEvent? _output;
    private MediaFoundationReader? _reader;
    public bool IsPlaying => _output?.PlaybackState == PlaybackState.Playing;

    public event Action<bool>? PlayingChanged;

    public void PlayUrl(string url)
    {
        Stop();
        _reader = new MediaFoundationReader(url);
        _output = new WaveOutEvent();
        _output.Init(_reader);
        _output.PlaybackStopped += (_,_) => PlayingChanged?.Invoke(false);
        _output.Play();
        PlayingChanged?.Invoke(true);
    }

    public void PauseResume()
    {
        if (_output == null) return;
        if (_output.PlaybackState == PlaybackState.Playing) _output.Pause();
        else _output.Play();
        PlayingChanged?.Invoke(IsPlaying);
    }

    public void Stop()
    {
        try { _output?.Stop(); } catch { }
        _output?.Dispose();
        _reader?.Dispose();
        _output = null;
        _reader = null;
        PlayingChanged?.Invoke(false);
    }

    public void Dispose() => Stop();
}
